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

namespace Horde\Http\Test\Unit;

use DateTimeImmutable;
use Horde\Http\CookieParser;
use Horde\Http\SameSite;
use Horde\Http\StrictCookie;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use DateTimeInterface;

#[CoversClass(CookieParser::class)]
class CookieParserTest extends TestCase
{
    // ---------------------------------------------------------------
    // formatSetCookie()
    // ---------------------------------------------------------------

    #[Test]
    public function formatMinimalCookie(): void
    {
        $header = CookieParser::formatSetCookie(
            new StrictCookie(cookieName: 'session', cookieValue: 'abc'),
        );
        self::assertSame('session=abc; Path=/; SameSite=Lax', $header);
    }

    #[Test]
    public function formatAllAttributes(): void
    {
        $expires = new DateTimeImmutable('2026-12-31T23:59:59+00:00');
        $cookie = new StrictCookie(
            cookieName: 'Horde',
            cookieValue: 'token123',
            cookieExpires: $expires,
            cookieMaxAge: 3600,
            cookieDomain: 'example.com',
            cookiePath: '/horde',
            cookieSecure: true,
            cookieHttpOnly: true,
            cookieSameSite: SameSite::Strict,
        );
        $header = CookieParser::formatSetCookie($cookie);

        self::assertStringContainsString('Horde=token123', $header);
        self::assertStringContainsString('Expires=Thu, 31 Dec 2026 23:59:59 GMT', $header);
        self::assertStringContainsString('Max-Age=3600', $header);
        self::assertStringContainsString('Domain=example.com', $header);
        self::assertStringContainsString('Path=/horde', $header);
        self::assertStringContainsString('Secure', $header);
        self::assertStringContainsString('HttpOnly', $header);
        self::assertStringContainsString('SameSite=Strict', $header);
    }

    #[Test]
    public function formatExpiresAlwaysInGmt(): void
    {
        // Cookie with an Expires set in a non-GMT timezone must be
        // emitted in GMT, per RFC 7231 IMF-fixdate.
        $expires = new DateTimeImmutable('2026-12-31T23:59:59+02:00');
        $cookie = new StrictCookie(
            cookieName: 'x',
            cookieExpires: $expires,
        );
        $header = CookieParser::formatSetCookie($cookie);
        self::assertStringContainsString('Expires=Thu, 31 Dec 2026 21:59:59 GMT', $header);
    }

    #[Test]
    public function formatDeletionCookie(): void
    {
        $deletion = (new StrictCookie(
            cookieName: 'session',
            cookieValue: 'abc',
            cookieDomain: 'example.com',
            cookiePath: '/horde',
        ))->deletion();
        $header = CookieParser::formatSetCookie($deletion);

        self::assertStringContainsString('session=', $header);
        self::assertStringContainsString('Max-Age=0', $header);
        self::assertStringContainsString('Domain=example.com', $header);
        self::assertStringContainsString('Path=/horde', $header);
        self::assertStringNotContainsString('Expires=', $header);
    }

    #[Test]
    public function formatOmitsDomainWhenNull(): void
    {
        $header = CookieParser::formatSetCookie(
            new StrictCookie(cookieName: 'x', cookieValue: 'y'),
        );
        self::assertStringNotContainsString('Domain=', $header);
    }

    #[Test]
    public function formatOmitsExpiresWhenNull(): void
    {
        $header = CookieParser::formatSetCookie(
            new StrictCookie(cookieName: 'x', cookieValue: 'y'),
        );
        self::assertStringNotContainsString('Expires=', $header);
    }

    #[Test]
    public function formatOmitsMaxAgeWhenNull(): void
    {
        $header = CookieParser::formatSetCookie(
            new StrictCookie(cookieName: 'x', cookieValue: 'y'),
        );
        self::assertStringNotContainsString('Max-Age=', $header);
    }

    // ---------------------------------------------------------------
    // formatCookie() (request header pair)
    // ---------------------------------------------------------------

    #[Test]
    public function formatCookieIsJustNameEqualsValue(): void
    {
        $cookie = new StrictCookie(
            cookieName: 'session',
            cookieValue: 'abc',
            cookieDomain: 'example.com',
            cookiePath: '/horde',
            cookieSecure: true,
            cookieHttpOnly: true,
        );
        // Cookie request header carries no attributes; only the pair.
        self::assertSame('session=abc', CookieParser::formatCookie($cookie));
    }

    #[Test]
    public function formatCookieEmptyValue(): void
    {
        self::assertSame('x=', CookieParser::formatCookie(
            new StrictCookie(cookieName: 'x'),
        ));
    }

    // ---------------------------------------------------------------
    // parseSetCookie()
    // ---------------------------------------------------------------

    #[Test]
    public function parseMinimalSetCookie(): void
    {
        $cookie = CookieParser::parseSetCookie('session=abc; Path=/; SameSite=Lax');
        self::assertSame('session', $cookie->name());
        self::assertSame('abc', $cookie->value());
        self::assertSame('/', $cookie->path());
        self::assertSame(SameSite::Lax, $cookie->sameSite());
    }

    #[Test]
    public function parseAllAttributes(): void
    {
        $header = 'Horde=token123; '
            . 'Expires=Thu, 31 Dec 2026 23:59:59 GMT; '
            . 'Max-Age=3600; '
            . 'Domain=example.com; '
            . 'Path=/horde; '
            . 'Secure; '
            . 'HttpOnly; '
            . 'SameSite=Strict';
        $cookie = CookieParser::parseSetCookie($header);

        self::assertSame('Horde', $cookie->name());
        self::assertSame('token123', $cookie->value());
        self::assertNotNull($cookie->expires());
        self::assertSame('Thu, 31 Dec 2026 23:59:59 +0000', $cookie->expires()->format('D, d M Y H:i:s O'));
        self::assertSame(3600, $cookie->maxAge());
        self::assertSame('example.com', $cookie->domain());
        self::assertSame('/horde', $cookie->path());
        self::assertTrue($cookie->secure());
        self::assertTrue($cookie->httpOnly());
        self::assertSame(SameSite::Strict, $cookie->sameSite());
    }

    #[Test]
    public function parseAttributeNamesAreCaseInsensitive(): void
    {
        $cookie = CookieParser::parseSetCookie(
            'x=y; max-age=10; PATH=/; secure; HTTPONLY; samesite=lax'
        );
        self::assertSame(10, $cookie->maxAge());
        self::assertSame('/', $cookie->path());
        self::assertTrue($cookie->secure());
        self::assertTrue($cookie->httpOnly());
        self::assertSame(SameSite::Lax, $cookie->sameSite());
    }

    #[Test]
    public function parseStripsLeadingDotFromDomain(): void
    {
        // Legacy syntax: ".example.com" was meaningful in RFC 2109; RFC
        // 6265bis says drop the dot, the cookie applies to subdomains
        // either way.
        $cookie = CookieParser::parseSetCookie('x=y; Domain=.example.com');
        self::assertSame('example.com', $cookie->domain());
    }

    #[Test]
    public function parseStripsQuotesFromValue(): void
    {
        // Some servers quote cookie values. RFC 6265 doesn't require us
        // to interpret the quotes, but stripping them lets us round-trip
        // through StrictCookie's strict octet check.
        $cookie = CookieParser::parseSetCookie('session="abc123"');
        self::assertSame('abc123', $cookie->value());
    }

    #[Test]
    public function parseDefaultsToLaxWhenSameSiteAbsent(): void
    {
        // Modern browsers default to Lax when SameSite is not specified.
        // Mirror that on parse.
        $cookie = CookieParser::parseSetCookie('x=y');
        self::assertSame(SameSite::Lax, $cookie->sameSite());
    }

    #[Test]
    public function parseUnknownSameSiteFallsBackToLax(): void
    {
        // Per RFC 6265bis, unrecognised SameSite values are treated as
        // if the attribute were absent : Lax in our model.
        $cookie = CookieParser::parseSetCookie('x=y; SameSite=Bogus');
        self::assertSame(SameSite::Lax, $cookie->sameSite());
    }

    #[Test]
    public function parseIgnoresUnknownAttributes(): void
    {
        $cookie = CookieParser::parseSetCookie('x=y; Path=/; Foo=bar; Bogus');
        self::assertSame('x', $cookie->name());
        self::assertSame('y', $cookie->value());
        self::assertSame('/', $cookie->path());
    }

    #[Test]
    public function parseIgnoresMalformedExpires(): void
    {
        // Bad Expires shouldn't reject the whole cookie; user agents are
        // tolerant of date parsing failures.
        $cookie = CookieParser::parseSetCookie('x=y; Expires=not-a-date');
        self::assertNull($cookie->expires());
    }

    #[Test]
    public function parseIgnoresMalformedMaxAge(): void
    {
        $cookie = CookieParser::parseSetCookie('x=y; Max-Age=lots');
        self::assertNull($cookie->maxAge());
    }

    #[Test]
    public function parseEmptyHeaderRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('empty');
        CookieParser::parseSetCookie('');
    }

    #[Test]
    public function parseHeaderWithoutEqualsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('name=value');
        CookieParser::parseSetCookie('justaname; Path=/');
    }

    // ---------------------------------------------------------------
    // parseCookieHeader() (request side)
    // ---------------------------------------------------------------

    #[Test]
    public function parseCookieHeaderReturnsPairs(): void
    {
        $pairs = CookieParser::parseCookieHeader('a=1; b=2; c=3');
        self::assertSame(['a' => '1', 'b' => '2', 'c' => '3'], $pairs);
    }

    #[Test]
    public function parseCookieHeaderEmpty(): void
    {
        self::assertSame([], CookieParser::parseCookieHeader(''));
        self::assertSame([], CookieParser::parseCookieHeader('   '));
    }

    #[Test]
    public function parseCookieHeaderRepeatedNamesLastWins(): void
    {
        // Browsers emit cookies in path-most-specific order. Last-wins
        // matches what most server frameworks do.
        $pairs = CookieParser::parseCookieHeader('a=1; a=2; a=3');
        self::assertSame(['a' => '3'], $pairs);
    }

    #[Test]
    public function parseCookieHeaderStripsQuotes(): void
    {
        $pairs = CookieParser::parseCookieHeader('a="quoted"; b=plain');
        self::assertSame(['a' => 'quoted', 'b' => 'plain'], $pairs);
    }

    #[Test]
    public function parseCookieHeaderAcceptsValuelessPair(): void
    {
        // A pair with no "=" is technically valid (empty value).
        $pairs = CookieParser::parseCookieHeader('flag; a=1');
        self::assertSame(['flag' => '', 'a' => '1'], $pairs);
    }

    // ---------------------------------------------------------------
    // Round-trip
    // ---------------------------------------------------------------

    #[Test]
    public function roundTripPreservesAllAttributes(): void
    {
        $original = new StrictCookie(
            cookieName: 'Horde',
            cookieValue: 'token123',
            cookieExpires: new DateTimeImmutable('2026-12-31T23:59:59+00:00'),
            cookieMaxAge: 3600,
            cookieDomain: 'example.com',
            cookiePath: '/horde',
            cookieSecure: true,
            cookieHttpOnly: true,
            cookieSameSite: SameSite::Strict,
        );
        $header = CookieParser::formatSetCookie($original);
        $parsed = CookieParser::parseSetCookie($header);

        self::assertSame($original->name(), $parsed->name());
        self::assertSame($original->value(), $parsed->value());
        self::assertSame(
            $original->expires()?->format(DateTimeInterface::ATOM),
            $parsed->expires()?->format(DateTimeInterface::ATOM),
        );
        self::assertSame($original->maxAge(), $parsed->maxAge());
        self::assertSame($original->domain(), $parsed->domain());
        self::assertSame($original->path(), $parsed->path());
        self::assertSame($original->secure(), $parsed->secure());
        self::assertSame($original->httpOnly(), $parsed->httpOnly());
        self::assertSame($original->sameSite(), $parsed->sameSite());
    }

    #[Test]
    public function roundTripMinimalCookie(): void
    {
        $original = new StrictCookie(cookieName: 'x', cookieValue: 'y');
        $parsed = CookieParser::parseSetCookie(CookieParser::formatSetCookie($original));
        self::assertSame('x', $parsed->name());
        self::assertSame('y', $parsed->value());
        self::assertSame('/', $parsed->path());
        self::assertSame(SameSite::Lax, $parsed->sameSite());
    }
}
