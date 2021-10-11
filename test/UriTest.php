<?php
namespace Horde\Http\Test;
use Phpunit\Framework\TestCase;
use Horde\Http\RequestFactory;
use Horde\Http\ServerRequest;
use Horde\Http\Stream;
use Horde\Http\Uri;
use Horde\Http\RequestImplementation;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;
use Psr\Http\Message\UriInterface;

class UriTest extends TestCase
{
    public function setUp(): void
    {
        $this->requestFactory = new RequestFactory;
    }

    public function testWithPathRemovesQueryString()
    {
        $path = '/hello/world';
        $queryStr = '?test=query';
        $uri = new Uri();
        $uri = $uri->withPath($path . $queryStr);
        $this->assertEquals($uri->getPath(), $path);
    }

    public function testWithPathWorksWithEmptyString()
    {
        $path = '';
        $uri = new Uri();
        $uri = $uri->withPath($path);
        $this->assertEquals($uri->getPath(), $path);
    }

    public function testWithPathRemovesHash()
    {
        $path = '/hello/world';
        $hash = '#test';
        $uri = new Uri();
        $uri = $uri->withPath($path . $hash);
        $this->assertEquals($uri->getPath(), $path);
    }
}
