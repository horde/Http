<?php

declare(strict_types=1);

/**
 * Copyright 2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (BSD). If you
 * did not receive this file, see http://www.horde.org/licenses/bsd.
 *
 * @category Horde
 * @license  http://www.horde.org/licenses/bsd BSD-2-Clause
 * @package  Http
 */

namespace Horde\Http;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use Exception;

/**
 * (De)serialiser for HTTP cookie headers.
 *
 * Produces and consumes the wire formats defined by RFC 6265:
 *
 * - {@see formatSetCookie()} / {@see parseSetCookie()} handle the
 *   server-side `Set-Cookie` header value (name, value, attributes).
 * - {@see formatCookie()} / {@see parseCookieHeader()} handle the
 *   client-side `Cookie` request header (name=value pairs only,
 *   no attributes).
 *
 * Stateless. All methods are static.
 *
 * Parsing produces {@see StrictCookie} instances; therefore the parser
 * inherits StrictCookie's validation contract. A header that does not
 * round-trip through StrictCookie's constructor is rejected with
 * {@see InvalidArgumentException}. Callers that need to consume cookies
 * with looser validation can implement {@see Cookie} directly and
 * reuse the parsing primitives via copy-and-adjust.
 */
final class CookieParser
{
    /**
     * RFC 7231 IMF-fixdate format used for the Expires attribute.
     *
     * Example: "Sun, 06 Nov 1994 08:49:37 GMT"
     */
    private const EXPIRES_FORMAT = 'D, d M Y H:i:s \G\M\T';

    /**
     * Format a {@see Cookie} as a Set-Cookie header value.
     */
    public static function formatSetCookie(Cookie $cookie): string
    {
        $parts = [$cookie->name() . '=' . $cookie->value()];

        $expires = $cookie->expires();
        if ($expires !== null) {
            $parts[] = 'Expires=' . $expires->setTimezone(new DateTimeZone('GMT'))
                ->format(self::EXPIRES_FORMAT);
        }

        $maxAge = $cookie->maxAge();
        if ($maxAge !== null) {
            $parts[] = 'Max-Age=' . $maxAge;
        }

        $domain = $cookie->domain();
        if ($domain !== null && $domain !== '') {
            $parts[] = 'Domain=' . $domain;
        }

        // Path always emitted; defaults to "/" and is meaningful even then.
        $parts[] = 'Path=' . $cookie->path();

        if ($cookie->secure()) {
            $parts[] = 'Secure';
        }

        if ($cookie->httpOnly()) {
            $parts[] = 'HttpOnly';
        }

        // SameSite always emitted; the wire token is the enum value.
        $parts[] = 'SameSite=' . $cookie->sameSite()->value;

        return implode('; ', $parts);
    }

    /**
     * Format a {@see Cookie} as a single name=value pair for use in
     * a Cookie request header. No attributes; just the pair.
     */
    public static function formatCookie(Cookie $cookie): string
    {
        return $cookie->name() . '=' . $cookie->value();
    }

    /**
     * Parse a single Set-Cookie header value into a {@see StrictCookie}.
     *
     * @throws InvalidArgumentException If the header is malformed or the
     *                                  resulting cookie violates strict
     *                                  validation.
     */
    public static function parseSetCookie(string $header): Cookie
    {
        $segments = self::splitAttributes($header);
        if ($segments === []) {
            throw new InvalidArgumentException('Set-Cookie header is empty.');
        }

        // First segment is name=value.
        $first = array_shift($segments);
        $eq = strpos($first, '=');
        if ($eq === false) {
            throw new InvalidArgumentException(sprintf(
                'Set-Cookie header missing name=value pair: "%s".',
                $header,
            ));
        }
        $name = trim(substr($first, 0, $eq));
        $value = self::stripQuotes(trim(substr($first, $eq + 1)));

        $expires = null;
        $maxAge = null;
        $domain = null;
        $path = '/';
        $secure = false;
        $httpOnly = false;
        $sameSite = SameSite::Lax;

        foreach ($segments as $segment) {
            $segment = trim($segment);
            if ($segment === '') {
                continue;
            }
            $eq = strpos($segment, '=');
            if ($eq === false) {
                $attrName = strtolower($segment);
                $attrValue = '';
            } else {
                $attrName = strtolower(trim(substr($segment, 0, $eq)));
                $attrValue = trim(substr($segment, $eq + 1));
            }

            switch ($attrName) {
                case 'expires':
                    $parsed = self::parseExpires($attrValue);
                    if ($parsed !== null) {
                        $expires = $parsed;
                    }
                    break;

                case 'max-age':
                    if (preg_match('/\A-?\d+\z/', $attrValue) === 1) {
                        $maxAge = (int) $attrValue;
                    }
                    break;

                case 'domain':
                    // RFC 6265bis: a leading dot in the attribute value is
                    // dropped by the user agent; we drop it on parse so the
                    // round trip lands on the canonical form.
                    if ($attrValue !== '') {
                        $domain = ltrim($attrValue, '.');
                    }
                    break;

                case 'path':
                    if ($attrValue !== '') {
                        $path = $attrValue;
                    }
                    break;

                case 'secure':
                    $secure = true;
                    break;

                case 'httponly':
                    $httpOnly = true;
                    break;

                case 'samesite':
                    $resolved = self::parseSameSite($attrValue);
                    if ($resolved !== null) {
                        $sameSite = $resolved;
                    }
                    break;

                    // Unknown attributes are ignored per RFC 6265 section 5.2.
            }
        }

        return new StrictCookie(
            cookieName: $name,
            cookieValue: $value,
            cookieExpires: $expires,
            cookieMaxAge: $maxAge,
            cookieDomain: $domain,
            cookiePath: $path,
            cookieSecure: $secure,
            cookieHttpOnly: $httpOnly,
            cookieSameSite: $sameSite,
        );
    }

    /**
     * Parse a Cookie request header into name=>value pairs.
     *
     * Browsers transmit cookies as `name1=value1; name2=value2; ...`.
     * Repeated names: last-wins, matching the convention browsers use.
     *
     * @return array<string, string>
     */
    public static function parseCookieHeader(string $header): array
    {
        $pairs = [];
        if (trim($header) === '') {
            return $pairs;
        }
        foreach (explode(';', $header) as $segment) {
            $segment = trim($segment);
            if ($segment === '') {
                continue;
            }
            $eq = strpos($segment, '=');
            if ($eq === false) {
                // Cookie request header pairs without "=" are technically
                // valid (the value is empty); accept as such.
                $pairs[$segment] = '';
                continue;
            }
            $name = trim(substr($segment, 0, $eq));
            $value = self::stripQuotes(trim(substr($segment, $eq + 1)));
            $pairs[$name] = $value;
        }
        return $pairs;
    }

    /**
     * Split a Set-Cookie header on attribute boundaries.
     *
     * Returns the name=value pair as the first element and one segment
     * per attribute thereafter. Empty segments are filtered out.
     *
     * @return list<string>
     */
    private static function splitAttributes(string $header): array
    {
        $segments = [];
        foreach (explode(';', $header) as $segment) {
            $segment = trim($segment);
            if ($segment !== '') {
                $segments[] = $segment;
            }
        }
        return $segments;
    }

    private static function stripQuotes(string $value): string
    {
        $len = strlen($value);
        if ($len >= 2 && $value[0] === '"' && $value[$len - 1] === '"') {
            return substr($value, 1, $len - 2);
        }
        return $value;
    }

    private static function parseExpires(string $value): ?DateTimeImmutable
    {
        if ($value === '') {
            return null;
        }
        // DateTimeImmutable understands the IMF-fixdate format and most
        // historical variants browsers may emit. Failure returns null,
        // which we treat as "ignore the attribute" rather than "reject
        // the cookie" : that matches user-agent leniency on Expires.
        try {
            return new DateTimeImmutable($value);
        } catch (Exception) {
            return null;
        }
    }

    private static function parseSameSite(string $value): ?SameSite
    {
        if ($value === '') {
            return null;
        }
        // Be tolerant of casing on parse: browsers and intermediaries
        // historically vary. We canonicalise to the enum cases.
        return match (strtolower($value)) {
            'strict' => SameSite::Strict,
            'lax' => SameSite::Lax,
            'none' => SameSite::None,
            default => null,
        };
    }
}
