<?php
/**
 * Copyright 2014-2017 Horde LLC (http://www.horde.org/)
 *
 * @category   Horde
 * @package    Http
 * @subpackage UnitTests
 * @license    http://www.horde.org/licenses/bsd
 */
namespace Horde\Http;
use Horde_Test_Case;
use \Horde_Http_Client;
use \Horde_Http_Exception;
use \Horde_Http;

/**
 * Unit test base.
 *
 * @category   Horde
 * @package    Http
 * @subpackage UnitTests
 * @license    http://www.horde.org/licenses/bsd
 */
class TestBase extends Horde_Test_Case
{
    protected $_server;

    protected static $_requestClass;

    public static function setUpBeforeClass(): void
    {
        $parts = explode('\\', get_called_class());
        $match = substr($parts[array_key_last($parts)], 0, -4);
        self::$_requestClass = 'Horde_Http_Request_' . $match;
    }

    public function setUp(): void
    {
        $config = self::getConfig('HTTP_TEST_CONFIG');
        if ($config && !empty($config['http']['server'])) {
            $this->_server = $config['http']['server'];
        }
        if (empty($this->_server)) {
            $this->_server = 'horde.org';
        }
    }

    public function testRequest()
    {
        $client = new Horde_Http_Client(
            array('request' => new self::$_requestClass())
        );
        $response = $client->get('http://' . $this->_server);

        $this->assertStringStartsWith('http', $response->uri);
        $this->assertStringStartsWith('1.', $response->httpVersion);
        $this->assertEquals(200, $response->code);
        $this->assertIsArray($response->headers);
        $this->assertIsString($response->getBody());
        $this->assertGreaterThan(0, strlen($response->getBody()));
        $this->assertIsResource($response->getStream());
        $this->assertStringMatchesFormat(
            '%s/%s',
            $response->getHeader('Content-Type')
        );
        $this->assertEquals(
            $response->getHeader('content-type'),
            $response->getHeader('Content-Type')
        );
        $this->assertArrayHasKey('content-type', $response->headers);
        $this->assertEquals(
            $response->getHeader('content-type'),
            $response->headers['content-type']
        );
    }

    public function testThrowsOnBadUri()
    {
        $client = new Horde_Http_Client([
            'request' => new self::$_requestClass()
        ]);
        $this->expectException(Horde_Http_Exception::class);
        $client->get('http://doesntexist/');
    }

    /**
     * @expectedException Horde_Http_Exception
     */
    public function testThrowsOnInvalidProxyType()
    {
        $client = new Horde_Http_Client(
            array(
                'request' => new self::$_requestClass(
                    array(
                        'proxyServer' => 'localhost',
                        'proxyType' => Horde_Http::PROXY_SOCKS4
                    )
                )
            )
        );
        $this->expectException(Horde_Http_Exception::class);
        $client->get('http://www.example.com/');
    }

    public function testReturnsResponseInsteadOfExceptionOn404()
    {
        $this->_skipMissingConfig();
        $client = new Horde_Http_Client(
            array('request' => new self::$_requestClass())
        );
        $response = $client->get('http://' . $this->_server . '/doesntexist');
        $this->assertEquals(404, $response->code);
    }

    public function testGetBodyAfter404()
    {
        $this->_skipMissingConfig();
        $client = new Horde_Http_Client(
            array('request' => new self::$_requestClass())
        );
        $response = $client->get('http://' . $this->_server . '/doesntexist');
        $content = $response->getBody();
        $this->assertGreaterThan(0, strlen($content));
    }

    protected function _skipMissingConfig()
    {
        if (empty($this->_server)) {
            $this->markTestSkipped('Missing configuration!');
        }
    }
}
