<?php
/**
 * Copyright 2007-2017 Horde LLC (http://www.horde.org/)
 *
 * @category   Horde
 * @package    Http
 * @subpackage UnitTests
 * @license    http://www.horde.org/licenses/bsd
 */
namespace Horde\Http;
use \Horde_Http_Client;

/**
 * Copyright 2007-2017 Horde LLC (http://www.horde.org/)
 *
 * @category   Horde
 * @package    Http
 * @subpackage UnitTests
 * @license    http://www.horde.org/licenses/bsd
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
