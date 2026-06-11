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
use Horde\Http\Cookie;
use Horde\Http\SameSite;
use Horde\Http\StrictCookie;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

#[CoversClass(StrictCookie::class)]
class StrictCookieTest extends TestCase
{
    #[Test]
    public function implementsCookieInterface(): void
    {
        $cookie = new StrictCookie(cookieName: 'session');
        self::assertInstanceOf(Cookie::class, $cookie);
    }

    // ---------------------------------------------------------------
    // Construction defaults
    // ---------------------------------------------------------------

    #[Test]
    public function defaultsAreSensible(): void
    {
        $cookie = new StrictCookie(cookieName: 'session');
        self::assertSame('session', $cookie->name());
        self::assertSame('', $cookie->value());
        self::assertNull($cookie->expires());
        self::assertNull($cookie->maxAge());
        self::assertNull($cookie->domain());
        self::assertSame('/', $cookie->path());
        self::assertFalse($cookie->secure());
        self::assertFalse($cookie->httpOnly());
        self::assertSame(SameSite::Lax, $cookie->sameSite());
    }

    #[Test]
    public function allFieldsRoundTrip(): void
    {
        $expires = new DateTimeImmutable('2026-12-31T23:59:59+00:00');
        $cookie = new StrictCookie(
            cookieName: 'Horde',
            cookieValue: 'abc123',
            cookieExpires: $expires,
            cookieMaxAge: 3600,
            cookieDomain: 'example.com',
            cookiePath: '/horde',
            cookieSecure: true,
            cookieHttpOnly: true,
            cookieSameSite: SameSite::Strict,
        );

        self::assertSame('Horde', $cookie->name());
        self::assertSame('abc123', $cookie->value());
        self::assertSame($expires, $cookie->expires());
        self::assertSame(3600, $cookie->maxAge());
        self::assertSame('example.com', $cookie->domain());
        self::assertSame('/horde', $cookie->path());
        self::assertTrue($cookie->secure());
        self::assertTrue($cookie->httpOnly());
        self::assertSame(SameSite::Strict, $cookie->sameSite());
    }

    // ---------------------------------------------------------------
    // Name validation
    // ---------------------------------------------------------------

    #[Test]
    public function emptyNameRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('must not be empty');
        new StrictCookie(cookieName: '');
    }

    #[Test]
    #[TestWith(['has space'])]
    #[TestWith(['has;semi'])]
    #[TestWith(['has,comma'])]
    #[TestWith(['has=equal'])]
    #[TestWith(['has"quote'])]
    #[TestWith(['has(paren'])]
    #[TestWith(["has\ttab"])]
    #[TestWith(["has\nnewline"])]
    public function nonTokenNameRejected(string $bad): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('RFC 7230 token characters');
        new StrictCookie(cookieName: $bad);
    }

    #[Test]
    #[TestWith(['session'])]
    #[TestWith(['SESSION'])]
    #[TestWith(['session_id'])]
    #[TestWith(['session-id'])]
    #[TestWith(['session.id'])]
    #[TestWith(['__Host-session'])]
    #[TestWith(['_csrf'])]
    public function validNamesAccepted(string $good): void
    {
        $cookie = new StrictCookie(cookieName: $good);
        self::assertSame($good, $cookie->name());
    }

    // ---------------------------------------------------------------
    // Value validation (strict-only encoding policy)
    // ---------------------------------------------------------------

    #[Test]
    #[TestWith(['hello; world'])]    // semicolon
    #[TestWith(['hello, world'])]    // comma
    #[TestWith(['hello world'])]     // space
    #[TestWith(['hello"world'])]     // quote
    #[TestWith(['hello\\world'])]    // backslash
    public function nonOctetValueRejected(string $bad): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('cookie-octet range');
        new StrictCookie(cookieName: 'x', cookieValue: $bad);
    }

    #[Test]
    public function preEncodedValueAccepted(): void
    {
        // Caller's responsibility to encode; we accept the result.
        $cookie = new StrictCookie(
            cookieName: 'x',
            cookieValue: rawurlencode('hello; world'),
        );
        self::assertSame('hello%3B%20world', $cookie->value());
    }

    #[Test]
    #[TestWith([''])]                                // empty allowed
    #[TestWith(['abc123'])]                          // alphanumeric
    #[TestWith(['eyJhbGciOiJIUzI1NiJ9.payload'])]    // JWT-shaped
    #[TestWith(['a_b-c.d~e/f'])]                     // safe punctuation
    public function validValuesAccepted(string $good): void
    {
        $cookie = new StrictCookie(cookieName: 'x', cookieValue: $good);
        self::assertSame($good, $cookie->value());
    }

    // ---------------------------------------------------------------
    // Other validation rules
    // ---------------------------------------------------------------

    #[Test]
    public function negativeMaxAgeRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('non-negative');
        new StrictCookie(cookieName: 'x', cookieMaxAge: -1);
    }

    #[Test]
    public function zeroMaxAgeAccepted(): void
    {
        // Max-Age=0 is the spec-defined deletion signal; not an error.
        $cookie = new StrictCookie(cookieName: 'x', cookieMaxAge: 0);
        self::assertSame(0, $cookie->maxAge());
    }

    #[Test]
    public function pathMustStartWithSlash(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Path must start with "/"');
        new StrictCookie(cookieName: 'x', cookiePath: 'horde');
    }

    #[Test]
    public function emptyPathRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Path must start with "/"');
        new StrictCookie(cookieName: 'x', cookiePath: '');
    }

    #[Test]
    public function sameSiteNoneRequiresSecure(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('SameSite=None');
        new StrictCookie(
            cookieName: 'x',
            cookieSameSite: SameSite::None,
            cookieSecure: false,
        );
    }

    #[Test]
    public function sameSiteNoneWithSecureAccepted(): void
    {
        $cookie = new StrictCookie(
            cookieName: 'x',
            cookieSecure: true,
            cookieSameSite: SameSite::None,
        );
        self::assertSame(SameSite::None, $cookie->sameSite());
        self::assertTrue($cookie->secure());
    }

    // ---------------------------------------------------------------
    // Immutability
    // ---------------------------------------------------------------

    #[Test]
    public function withValueReturnsNewInstance(): void
    {
        $original = new StrictCookie(cookieName: 'x', cookieValue: 'a');
        $changed = $original->withValue('b');

        self::assertNotSame($original, $changed);
        self::assertSame('a', $original->value());
        self::assertSame('b', $changed->value());
    }

    #[Test]
    public function withSameValueReturnsSameInstance(): void
    {
        // Optimisation: avoid pointless allocation when nothing changes.
        $original = new StrictCookie(cookieName: 'x', cookieValue: 'a');
        self::assertSame($original, $original->withValue('a'));
    }

    #[Test]
    public function allWithMethodsPreserveOtherFields(): void
    {
        $expires = new DateTimeImmutable('2026-12-31T23:59:59+00:00');
        $original = new StrictCookie(
            cookieName: 'Horde',
            cookieValue: 'abc',
            cookieExpires: $expires,
            cookieMaxAge: 3600,
            cookieDomain: 'example.com',
            cookiePath: '/horde',
            cookieSecure: true,
            cookieHttpOnly: true,
            cookieSameSite: SameSite::Strict,
        );

        $assertions = [
            'value' => fn() => $original->withValue('xyz'),
            'expires' => fn() => $original->withExpires(new DateTimeImmutable('2027-01-01T00:00:00+00:00')),
            'maxAge' => fn() => $original->withMaxAge(7200),
            'domain' => fn() => $original->withDomain('other.example.com'),
            'path' => fn() => $original->withPath('/other'),
            'secure' => fn() => $original->withSecure(false),
            'httpOnly' => fn() => $original->withHttpOnly(false),
            'sameSite' => fn() => $original->withSameSite(SameSite::Lax),
        ];

        foreach ($assertions as $changedField => $build) {
            $changed = $build();
            self::assertNotSame($original, $changed, "with{$changedField} returned same instance");
            self::assertSame('Horde', $changed->name(), "with{$changedField} dropped name");

            // Spot-check that one unrelated field per call survived.
            if ($changedField !== 'value') {
                self::assertSame('abc', $changed->value(), "with{$changedField} clobbered value");
            }
            if ($changedField !== 'path') {
                self::assertSame('/horde', $changed->path(), "with{$changedField} clobbered path");
            }
            if ($changedField !== 'sameSite') {
                self::assertSame(SameSite::Strict, $changed->sameSite(), "with{$changedField} clobbered sameSite");
            }
        }
    }

    #[Test]
    public function withSecureCanRelaxFromNoneToLax(): void
    {
        // SameSite=None requires Secure. Removing Secure first requires
        // also relaxing SameSite. Order matters.
        $cookie = new StrictCookie(
            cookieName: 'x',
            cookieSecure: true,
            cookieSameSite: SameSite::None,
        );
        $relaxed = $cookie->withSameSite(SameSite::Lax)->withSecure(false);
        self::assertFalse($relaxed->secure());
        self::assertSame(SameSite::Lax, $relaxed->sameSite());
    }

    #[Test]
    public function withSecureFalseOnSameSiteNoneStillThrows(): void
    {
        // Caller forgot to relax SameSite first: validation on the new
        // instance fires.
        $cookie = new StrictCookie(
            cookieName: 'x',
            cookieSecure: true,
            cookieSameSite: SameSite::None,
        );
        $this->expectException(InvalidArgumentException::class);
        $cookie->withSecure(false);
    }

    // ---------------------------------------------------------------
    // deletion()
    // ---------------------------------------------------------------

    #[Test]
    public function deletionPreservesScope(): void
    {
        $original = new StrictCookie(
            cookieName: 'session',
            cookieValue: 'abc123',
            cookieDomain: 'example.com',
            cookiePath: '/horde',
            cookieSecure: true,
            cookieHttpOnly: true,
        );
        $deletion = $original->deletion();

        self::assertSame('session', $deletion->name());
        self::assertSame('', $deletion->value());
        self::assertSame(0, $deletion->maxAge());
        self::assertNull($deletion->expires());
        self::assertSame('example.com', $deletion->domain());
        self::assertSame('/horde', $deletion->path());
        self::assertTrue($deletion->secure());
        self::assertTrue($deletion->httpOnly());
    }

    #[Test]
    public function deletionReturnsNewInstance(): void
    {
        $original = new StrictCookie(cookieName: 'x', cookieValue: 'a');
        self::assertNotSame($original, $original->deletion());
    }

    // ---------------------------------------------------------------
    // Formatter delegation (substance covered in CookieParserTest)
    // ---------------------------------------------------------------

    #[Test]
    public function toSetCookieHeaderDelegates(): void
    {
        // Just check it returns a non-empty string with the name; the full
        // wire format is exercised in CookieParserTest.
        $header = (new StrictCookie(cookieName: 'session', cookieValue: 'abc'))
            ->toSetCookieHeader();
        self::assertStringContainsString('session=abc', $header);
        self::assertStringContainsString('Path=/', $header);
        self::assertStringContainsString('SameSite=Lax', $header);
    }

    #[Test]
    public function toCookieHeaderDelegates(): void
    {
        $pair = (new StrictCookie(cookieName: 'session', cookieValue: 'abc'))
            ->toCookieHeader();
        self::assertSame('session=abc', $pair);
    }
}
