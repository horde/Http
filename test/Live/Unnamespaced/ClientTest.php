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

use Horde\Test\TestCase;
use Horde_Http_Request_Mock;
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
class ClientTest extends TestCase
{
    public function testGetTimeout()
    {
        $request = new Horde_Http_Request_Mock();
        $this->assertEquals(5, $request->timeout);
    }

    public function testSetTimeout()
    {
        $request = new Horde_Http_Request_Mock();
        $client = new Horde_Http_Client(
            ['request' => $request]
        );
        $client->{'request.timeout'} = 10;
        $this->assertEquals(10, $request->timeout);
    }

    public function testSetUnknownOption()
    {
        $this->expectException('Horde_Http_Exception');
        $request = new Horde_Http_Request_Mock();
        $client = new Horde_Http_Client(
            ['request' => $request]
        );
        $client->timeout = 10;
    }
}
