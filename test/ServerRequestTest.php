<?php
namespace Horde\Http\Test;
use Phpunit\Framework\TestCase;
use Horde\Http\RequestFactory;
use Psr\Http\Message\ServerRequestInterface;

class ServerRequestTest extends TestCase
{
    public function setUp(): void
    {
        $this->requestFactory = new RequestFactory;
    }

    public function testCreateFullUrlRequest()
    {
        $request = $this->requestFactory->createServerRequest('GET', 'https://www.horde.org/');
        $this->assertInstanceOf(ServerRequestInterface::class, $request);
    }

    public function testCreateRelativeUrlRequest()
    {
        $request = $this->requestFactory->createServerRequest('GET', '/foo');
        $this->assertInstanceOf(ServerRequestInterface::class, $request);
    }
}