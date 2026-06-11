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

use Horde\Http\SameSite;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(SameSite::class)]
class SameSiteTest extends TestCase
{
    #[Test]
    public function strictMatchesWireToken(): void
    {
        self::assertSame('Strict', SameSite::Strict->value);
    }

    #[Test]
    public function laxMatchesWireToken(): void
    {
        self::assertSame('Lax', SameSite::Lax->value);
    }

    #[Test]
    public function noneMatchesWireToken(): void
    {
        self::assertSame('None', SameSite::None->value);
    }

    #[Test]
    public function fromAcceptsCanonicalCases(): void
    {
        self::assertSame(SameSite::Strict, SameSite::from('Strict'));
        self::assertSame(SameSite::Lax, SameSite::from('Lax'));
        self::assertSame(SameSite::None, SameSite::from('None'));
    }

    #[Test]
    public function fromIsCaseSensitive(): void
    {
        // Wire format uses capitalized tokens; lower- or upper-case variants
        // are not part of the contract. tryFrom returns null for those.
        self::assertNull(SameSite::tryFrom('strict'));
        self::assertNull(SameSite::tryFrom('LAX'));
        self::assertNull(SameSite::tryFrom('none'));
    }

    #[Test]
    public function casesReturnsAllThree(): void
    {
        $cases = SameSite::cases();
        self::assertCount(3, $cases);
        self::assertContains(SameSite::Strict, $cases);
        self::assertContains(SameSite::Lax, $cases);
        self::assertContains(SameSite::None, $cases);
    }
}
