<?php

namespace Horde\Http\Test;

use AssertionError;
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
use ReflectionMethod;
use InvalidArgumentException;

class UriTest extends TestCase
{
    private Uri $url;

    public function setUp(): void
    {
        $this->url = new Uri($url = 'http://hans:flammenwerfer@www.testsite.com:21/testpath?q=test#hashtest');
        $this->requestFactory = new RequestFactory();
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

    public function testToString()
    {
        $url = 'http://www.testsite.com/testpath?q=test#hashtest';
        $uri = new Uri($url);
        $this->assertEquals($url, (string) $uri);
    }

    public function testWithValidPort()
    {
        $port = '56564';
        $uri = new Uri();
        $uri = $uri->withPort($port);
        $this->assertEquals($uri->getPort(), $port);
    }

    public function testWithValidPortMax()
    {
        $port = '65535';
        $uri = new Uri();
        $uri = $uri->withPort($port);
        $this->assertEquals($uri->getPort(), $port);
    }

    public function testWithValidPortMin()
    {
        $port = '0';
        $uri = new Uri();
        $this->expectException(InvalidArgumentException::class);
        $uri = $uri->withPort($port);
    }

    public function testWithInvalidPortUpper()
    {
        $port = '65536';
        $uri = new Uri();
        $this->expectException(InvalidArgumentException::class);
        $uri = $uri->withPort($port);
    }

    public function testWithInvalidPortLower()
    {
        $port = '-631';
        $uri = new Uri();
        $this->expectException(InvalidArgumentException::class);
        $uri = $uri->withPort($port);
    }

    public function testWithNullPort()
    {
        $port = null;
        $uri = new Uri();
        $uri = $uri->withPort($port);
        $this->assertNull($uri->getPort());
    }

    public function testWithValidLowerHost()
    {
        $host = 'groupware';
        $uri = new Uri();
        $uri = $uri->withHost($host);
        $this->assertEquals($uri->getHost(), $host);
    }

    public function testWithValidUpperHost()
    {
        $host = 'GroupWare';
        $uri = new Uri();
        $uri = $uri->withHost($host);
        $this->assertEquals($uri->getHost(), 'groupware');
    }

    public function testWithValidHyphen()
    {
        $host = 'Group-wa-re';
        $uri = new Uri();
        $uri = $uri->withHost($host);
        $this->assertSame($uri->getHost(), 'group-wa-re');
    }

    public function testWithValidDot()
    {
        $host = 'Group.wa.re';
        $uri = new Uri();
        $uri = $uri->withHost($host);
        $this->assertSame($uri->getHost(), 'group.wa.re');
    }

    public function testWithValidHyphenAndDot()
    {
        $host = 'Group-wa.re';
        $uri = new Uri();
        $uri = $uri->withHost($host);
        $this->assertSame($uri->getHost(), 'group-wa.re');
    }

    public function testWithInvalidHostSpecial()
    {
        $host = 'group¹ware';
        $uri = new Uri();
        $this->expectException(InvalidArgumentException::class);
        $uri = $uri->withHost($host);
    }

    public function testWithInvalidHostHash()
    {
        $host = 'group##ware';
        $uri = new Uri();
        $this->expectException(InvalidArgumentException::class);
        $uri = $uri->withHost($host);
    }

    public function testWithInvalidHostStartHyphen()
    {
        $host = '-groupware';
        $uri = new Uri();
        $this->expectException(InvalidArgumentException::class);
        $uri = $uri->withHost($host);
    }

    public function testWithEmptyHost()
    {
        $host = '';
        $uri = new Uri();
        $uri = $uri->withHost($host);
        $this->assertSame($uri->getHost(), '');
    }

    public function testWithfragmentString()
    {
        $fragment = 'print';
        $uri = new Uri();
        $uri = $uri->withFragment($fragment);
        $this->assertEquals($uri->getFragment(), $fragment);
    }

    public function testWithEmpytFragmentString()
    {
        $fragment = '';
        $uri = new Uri();
        $uri = $uri->withFragment($fragment);
        $this->assertSame($uri->getFragment(), '');
    }

    public function testWithUserInfoValid()
    {
        $uri = new Uri();
        $user = 'test';
        $pass = '1234';
        $uri = $uri->withUserInfo($user, $pass);
        $this->assertEquals($uri->getUserInfo(), 'test:1234');
    }


    public function testWithUserInfoValidEmptyPass()
    {
        $uri = new Uri();
        $user = 'test';
        $uri = $uri->withUserInfo($user);
        $this->assertEquals($uri->getUserInfo(), $user);
    }

    public function testWithSchemeValidLower()
    {
        $uri = new Uri();
        $scheme = 'feed';
        $uri = $uri->withScheme($scheme);
        $this->assertEquals($uri->getScheme(), $scheme);
    }

    public function testWithSchemeValidUpper()
    {
        $uri = new Uri();
        $scheme = 'FeEd';
        $uri = $uri->withScheme($scheme);
        $this->assertEquals($uri->getScheme(), 'feed');
    }

    public function testWithSchemeEmpty()
    {
        $uri = new Uri();
        $scheme = '';
        $uri = $uri->withScheme($scheme);
        $this->assertSame($uri->getScheme(), '');
    }

    public function testWithAuthorityValid()
    {
        $uri = new Uri();
        $host = 'groupware';
        $user = 'test';
        $port = '12345';
        $uri = $uri->withHost($host);
        $uri = $uri->withUserInfo($user);
        $uri = $uri->withPort($port);
        /**
         * The authority syntax of the URI is:
         * [user-info@]host[:port]
         */
        $this->assertEquals($uri->getAuthority(), 'test@groupware:12345');
    }

    public function testWithAuthorityHostEmpty()
    {
        $uri = new Uri();
        $host = '';
        $user = 'test';
        $port = '12345';
        $uri = $uri->withHost($host);
        $uri = $uri->withUserInfo($user);
        $uri = $uri->withPort($port);
        $this->assertSame($uri->getAuthority(), '');
    }

    public function testWithSchemeImmutableCheck()
    {
        $dumy = $this->url->withScheme('feed');
        $this->assertSame($this->url->getScheme(), 'http');
        $this->assertSame($dumy->getScheme(), 'feed');
    }

    public function testWithUserInfoImmutableCheck()
    {
        $dumy = $this->url->withUserInfo('test', '1234');
        $this->assertSame($this->url->getUserInfo(), 'hans:flammenwerfer');
        $this->assertEquals($dumy->getUserInfo(), 'test:1234');
    }

    public function testWithHostImmutableCheck()
    {
        $dumy = $this->url->withHost('www.groupware.com');
        $this->assertSame($this->url->getHost(), 'www.testsite.com');
        $this->assertSame($dumy->getHost(), 'www.groupware.com');
    }

    public function testWithPortImmutableCheck()
    {
        $dumy = $this->url->withPort(631);
        $this->assertSame($this->url->getPort(), 21);
        $this->assertSame($dumy->getPort(), 631);
    }

    public function testWithPathImmutableCheck()
    {
        $dumy = $this->url->withPath('/test/path');
        $this->assertSame($this->url->getPath(), '/testpath');
        $this->assertSame($dumy->getPath(), '/test/path');
    }

    public function testWithQueryImmutableCheck()
    {
        $dumy = $this->url->withQuery('s=test');
        $this->assertSame($this->url->getQuery(), 'q=test');
        $this->assertSame($dumy->getQuery(), 's=test');
    }

    public function testWithFragmentImmutableCheck()
    {
        $dumy = $this->url->withFragment('cookietest');
        $this->assertSame($this->url->getFragment(), 'hashtest');
        $this->assertSame($dumy->getFragment(), 'cookietest');
    }
}
