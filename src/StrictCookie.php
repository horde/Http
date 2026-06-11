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
use InvalidArgumentException;

/**
 * Strict default {@see Cookie} implementation.
 *
 * Validates RFC 6265 constraints in the constructor and throws
 * {@see InvalidArgumentException} on any violation. Callers that want
 * looser validation implement {@see Cookie} themselves or wrap a
 * StrictCookie instance; this class is intentionally final and
 * authoritative for "well-formed cookie."
 *
 * Encoding policy: strict. Cookie values must contain only RFC 6265
 * cookie-octets. Callers that need to store arbitrary bytes encode
 * explicitly (URL-encoding, base64, etc.) before construction. There
 * is no silent encoding.
 */
final class StrictCookie implements Cookie
{
    /**
     * RFC 7230 token character set, used for cookie names.
     *
     * tchar = "!" / "#" / "$" / "%" / "&" / "'" / "*" / "+" / "-" / "."
     *       / "^" / "_" / "`" / "|" / "~" / DIGIT / ALPHA
     */
    private const NAME_PATTERN = '/\A[!#$%&\'*+\-.^_`|~0-9A-Za-z]+\z/';

    /**
     * RFC 6265 cookie-octet character set, used for cookie values.
     *
     * cookie-octet = %x21 / %x23-2B / %x2D-3A / %x3C-5B / %x5D-7E
     * (Printable US-ASCII excluding ",", ";", DQUOTE, "\", SP, TAB, CTL.)
     */
    private const VALUE_PATTERN = '/\A[\x21\x23-\x2B\x2D-\x3A\x3C-\x5B\x5D-\x7E]*\z/';

    /**
     * @throws InvalidArgumentException When any argument violates the
     *                                  cookie's well-formed-ness contract.
     */
    public function __construct(
        private readonly string $cookieName,
        private readonly string $cookieValue = '',
        private readonly ?DateTimeImmutable $cookieExpires = null,
        private readonly ?int $cookieMaxAge = null,
        private readonly ?string $cookieDomain = null,
        private readonly string $cookiePath = '/',
        private readonly bool $cookieSecure = false,
        private readonly bool $cookieHttpOnly = false,
        private readonly SameSite $cookieSameSite = SameSite::Lax,
    ) {
        $this->validate();
    }

    public function name(): string
    {
        return $this->cookieName;
    }

    public function value(): string
    {
        return $this->cookieValue;
    }

    public function expires(): ?DateTimeImmutable
    {
        return $this->cookieExpires;
    }

    public function maxAge(): ?int
    {
        return $this->cookieMaxAge;
    }

    public function domain(): ?string
    {
        return $this->cookieDomain;
    }

    public function path(): string
    {
        return $this->cookiePath;
    }

    public function secure(): bool
    {
        return $this->cookieSecure;
    }

    public function httpOnly(): bool
    {
        return $this->cookieHttpOnly;
    }

    public function sameSite(): SameSite
    {
        return $this->cookieSameSite;
    }

    public function withValue(string $value): Cookie
    {
        if ($value === $this->cookieValue) {
            return $this;
        }
        return new self(
            $this->cookieName,
            $value,
            $this->cookieExpires,
            $this->cookieMaxAge,
            $this->cookieDomain,
            $this->cookiePath,
            $this->cookieSecure,
            $this->cookieHttpOnly,
            $this->cookieSameSite,
        );
    }

    public function withExpires(?DateTimeImmutable $expires): Cookie
    {
        return new self(
            $this->cookieName,
            $this->cookieValue,
            $expires,
            $this->cookieMaxAge,
            $this->cookieDomain,
            $this->cookiePath,
            $this->cookieSecure,
            $this->cookieHttpOnly,
            $this->cookieSameSite,
        );
    }

    public function withMaxAge(?int $maxAge): Cookie
    {
        return new self(
            $this->cookieName,
            $this->cookieValue,
            $this->cookieExpires,
            $maxAge,
            $this->cookieDomain,
            $this->cookiePath,
            $this->cookieSecure,
            $this->cookieHttpOnly,
            $this->cookieSameSite,
        );
    }

    public function withDomain(?string $domain): Cookie
    {
        return new self(
            $this->cookieName,
            $this->cookieValue,
            $this->cookieExpires,
            $this->cookieMaxAge,
            $domain,
            $this->cookiePath,
            $this->cookieSecure,
            $this->cookieHttpOnly,
            $this->cookieSameSite,
        );
    }

    public function withPath(string $path): Cookie
    {
        return new self(
            $this->cookieName,
            $this->cookieValue,
            $this->cookieExpires,
            $this->cookieMaxAge,
            $this->cookieDomain,
            $path,
            $this->cookieSecure,
            $this->cookieHttpOnly,
            $this->cookieSameSite,
        );
    }

    public function withSecure(bool $secure = true): Cookie
    {
        if ($secure === $this->cookieSecure) {
            return $this;
        }
        return new self(
            $this->cookieName,
            $this->cookieValue,
            $this->cookieExpires,
            $this->cookieMaxAge,
            $this->cookieDomain,
            $this->cookiePath,
            $secure,
            $this->cookieHttpOnly,
            $this->cookieSameSite,
        );
    }

    public function withHttpOnly(bool $httpOnly = true): Cookie
    {
        if ($httpOnly === $this->cookieHttpOnly) {
            return $this;
        }
        return new self(
            $this->cookieName,
            $this->cookieValue,
            $this->cookieExpires,
            $this->cookieMaxAge,
            $this->cookieDomain,
            $this->cookiePath,
            $this->cookieSecure,
            $httpOnly,
            $this->cookieSameSite,
        );
    }

    public function withSameSite(SameSite $sameSite): Cookie
    {
        if ($sameSite === $this->cookieSameSite) {
            return $this;
        }
        return new self(
            $this->cookieName,
            $this->cookieValue,
            $this->cookieExpires,
            $this->cookieMaxAge,
            $this->cookieDomain,
            $this->cookiePath,
            $this->cookieSecure,
            $this->cookieHttpOnly,
            $sameSite,
        );
    }

    public function toSetCookieHeader(): string
    {
        return CookieParser::formatSetCookie($this);
    }

    public function toCookieHeader(): string
    {
        return CookieParser::formatCookie($this);
    }

    public function deletion(): Cookie
    {
        return new self(
            cookieName: $this->cookieName,
            cookieValue: '',
            cookieExpires: null,
            cookieMaxAge: 0,
            cookieDomain: $this->cookieDomain,
            cookiePath: $this->cookiePath,
            cookieSecure: $this->cookieSecure,
            cookieHttpOnly: $this->cookieHttpOnly,
            cookieSameSite: $this->cookieSameSite,
        );
    }

    private function validate(): void
    {
        if ($this->cookieName === '') {
            throw new InvalidArgumentException('Cookie name must not be empty.');
        }
        if (preg_match(self::NAME_PATTERN, $this->cookieName) !== 1) {
            throw new InvalidArgumentException(sprintf(
                'Cookie name "%s" contains characters that are not RFC 7230 token characters. '
                . 'Allowed: ALPHA / DIGIT / "!" / "#" / "$" / "%%" / "&" / "\'" / "*" / "+" / "-" / "." / '
                . '"^" / "_" / "`" / "|" / "~".',
                $this->cookieName,
            ));
        }

        if (preg_match(self::VALUE_PATTERN, $this->cookieValue) !== 1) {
            throw new InvalidArgumentException(sprintf(
                'Cookie value for "%s" contains octets outside the RFC 6265 cookie-octet range. '
                . 'Encode the value (URL-encoding, base64, etc.) before construction; '
                . 'StrictCookie does not perform silent encoding.',
                $this->cookieName,
            ));
        }

        if ($this->cookieMaxAge !== null && $this->cookieMaxAge < 0) {
            throw new InvalidArgumentException(sprintf(
                'Cookie Max-Age must be non-negative; got %d. Use 0 to delete the cookie.',
                $this->cookieMaxAge,
            ));
        }

        if ($this->cookiePath === '' || $this->cookiePath[0] !== '/') {
            throw new InvalidArgumentException(sprintf(
                'Cookie Path must start with "/"; got "%s".',
                $this->cookiePath,
            ));
        }

        if ($this->cookieSameSite === SameSite::None && !$this->cookieSecure) {
            throw new InvalidArgumentException(
                'Cookie with SameSite=None must also be marked Secure. '
                . 'Modern browsers reject the combination otherwise.'
            );
        }
    }
}
