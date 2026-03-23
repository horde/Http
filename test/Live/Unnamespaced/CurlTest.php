<?php

/**
 * Copyright 2007-2026 Horde LLC (http://www.horde.org/)
 *
 * @category   Horde
 * @package    Http
 * @subpackage UnitTests
 * @license    http://www.horde.org/licenses/bsd
 */

namespace Horde\Http\Test\Live\Unnamespaced;

use Horde_Http_Client;

/**
 * Copyright 2007-2026 Horde LLC (http://www.horde.org/)
 *
 * @category   Horde
 * @package    Http
 * @subpackage UnitTests
 * @license    http://www.horde.org/licenses/bsd
 * @coversNothing
 */
class CurlTest extends TestBase
{
    public function setUp(): void
    {
        if (!function_exists('\curl_exec')) {
            $this->markTestSkipped('Missing PHP extension "curl"!');
        }
        parent::setUp();
    }
}
