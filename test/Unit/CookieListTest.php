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

use Horde\Http\CookieList;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(CookieList::class)]
class CookieListTest extends TestCase
{
    #[Test]
    public function fromCookieParamsRoundTrips(): void
    {
        $list = CookieList::fromCookieParams(['a' => '1', 'b' => '2']);
        self::assertSame(['a' => '1', 'b' => '2'], $list->toArray());
    }

    #[Test]
    public function fromCookieHeaderParsesPairs(): void
    {
        $list = CookieList::fromCookieHeader('a=1; b=2; c=3');
        self::assertSame('1', $list->get('a'));
        self::assertSame('2', $list->get('b'));
        self::assertSame('3', $list->get('c'));
    }

    #[Test]
    public function fromCookieHeaderEmpty(): void
    {
        self::assertSame([], CookieList::fromCookieHeader('')->toArray());
        self::assertSame([], CookieList::fromCookieHeader('   ')->toArray());
    }

    #[Test]
    public function getReturnsNullWhenAbsent(): void
    {
        $list = CookieList::fromCookieParams(['a' => '1']);
        self::assertNull($list->get('missing'));
    }

    #[Test]
    public function getReturnsValueWhenPresent(): void
    {
        $list = CookieList::fromCookieParams(['session' => 'abc123']);
        self::assertSame('abc123', $list->get('session'));
    }

    #[Test]
    public function hasDistinguishesPresentFromAbsent(): void
    {
        $list = CookieList::fromCookieParams(['a' => '1', 'empty' => '']);
        self::assertTrue($list->has('a'));
        self::assertTrue($list->has('empty'));     // present-but-empty
        self::assertFalse($list->has('missing'));
    }

    #[Test]
    public function namesReturnsAllNamesInOrder(): void
    {
        $list = CookieList::fromCookieParams(['z' => '1', 'a' => '2', 'm' => '3']);
        self::assertSame(['z', 'a', 'm'], $list->names());
    }

    #[Test]
    public function toArrayReturnsUnderlyingMap(): void
    {
        $params = ['a' => '1', 'b' => '2'];
        $list = CookieList::fromCookieParams($params);
        self::assertSame($params, $list->toArray());
    }

    #[Test]
    public function countReturnsNumberOfCookies(): void
    {
        self::assertSame(0, CookieList::fromCookieParams([])->count());
        self::assertSame(1, CookieList::fromCookieParams(['a' => '1'])->count());
        self::assertSame(3, CookieList::fromCookieParams(['a' => '1', 'b' => '2', 'c' => '3'])->count());
    }

    #[Test]
    public function constructAcceptsEmptyMap(): void
    {
        $list = new CookieList([]);
        self::assertSame([], $list->toArray());
        self::assertSame([], $list->names());
        self::assertFalse($list->has('anything'));
        self::assertNull($list->get('anything'));
    }

    #[Test]
    public function fromCookieHeaderRepeatedNamesLastWins(): void
    {
        // Delegated to CookieParser; tested here for the public contract.
        $list = CookieList::fromCookieHeader('a=1; a=2; a=3');
        self::assertSame('3', $list->get('a'));
    }
}
