<?php
namespace Horde\Http\Test;

use Phpunit\Framework\TestCase;
use Horde\Http\RequestFactory;
use Horde\Http\ServerRequest;
use Horde\Http\Stream;
use InvalidArgumentException;
use Horde\Http\Uri;
use Horde\Http\RequestImplementation;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;
use Psr\Http\Message\UriInterface;

class ServerRequestTest extends TestCase
{
    public function setUp(): void
    {
        $this->requestFactory = new RequestFactory;
    }

    private function arrayValuesToArrays($arr)
    {
        $newArr = [];
        foreach ($arr as $key => $val) {
            if (!is_array($val)) {
                $val = [$val];
            }
            $newArr[$key] = $val;
        }
        return $newArr;
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

    // MessageImplementation
    public function testHeaderDoesNotThrowErrorsForAllowedCharactersInValue()
    {
        $headerName = 'Testheadersssbla';
        $headerValue = 'Trestvalue';
        $headerValue .=  chr(0x20);
        $headerValue .=  chr(0x05);
        $headerValue .=  chr(0x04);
        $headerValue .=  chr(0x03);
        $headerValue .=  chr(0x1A);
        $headerValue .=  chr(0x1D);
        $headers = [];
        $headers[$headerName] = $headerValue;
        $request = new ServerRequest('GET', '/foo', $headers);
        $this->assertEquals([$headerValue], $request->getHeader($headerName));
    }

    public function testHeaderThrowsExceptionWhen0a0d00InValue()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectDeprecationMessageMatches('/0a, 0d, 00$/');
        $headerName = 'Testheadersssbla';
        $headerValue = 'Trestvalue';
        $headerValue .=  chr(0x0A);
        $headerValue .=  chr(0x0D);
        $headerValue .=  chr(0x00);
        $headers = [];
        $headers[$headerName] = $headerValue;
        $request = new ServerRequest('GET', '/foo', $headers);
    }

    public function testHeaderThrowsExceptionWhenAsciiCharactersTill32InName()
    {   // This request should be refused due to invalid ascii characters in $headerName
        $this->expectException(InvalidArgumentException::class);
        $this->expectDeprecationMessageMatches('/01, 05, 0a, 00, 20$/');
        $headerName = 'TestHeader';
        $headerName =  chr(0x01);
        $headerName .=  chr(0x05);
        $headerName .=  chr(0x0A);
        $headerName .=  chr(0x00);
        $headerName .=  chr(0x20);
        $headerValue = 'TestValue';
        $headers = [];
        $headers[$headerName] = $headerValue;
        $request = new ServerRequest('GET', '/foo', $headers);
    }

    public function testGetProtocolVersionIsString()
    {
        $version = 2.3;
        $request = new ServerRequest('GET', '/foo');
        $request = $request->withProtocolVersion($version);
        $this->assertEquals((string) $version, $request->getProtocolVersion());
    }

    public function testGetProtocolVersionCreatesWithNewVersion()
    {
        $newVersion = '2.3';
        $request = new ServerRequest('GET', '/foo');
        $request = $request->withProtocolVersion($newVersion);
        $this->assertEquals($newVersion, $request->getProtocolVersion());
    }

    public function testGetProtocolVersionPreservesMessage()
    {
        $oldVersion = '1.5';
        $newVersion = '2.3';
        $request = new ServerRequest('GET', '/foo', [], null, $oldVersion);
        $newRequest = $request->withProtocolVersion($newVersion);
        $this->assertEquals($oldVersion, $request->getProtocolVersion());
    }

    public function testGetHeaderNotFoundReturnsEmptyArray()
    {
        $request = new ServerRequest('GET', '/foo');
        $this->assertEquals([], $request->getHeader('NotFoundHeaderName'));
    }

    public function testGetHeaderIsCaseInsensitive()
    {
        $headerName = 'TestHeader';
        $headerValue = 'TestValue';
        $headers = [];
        $headers[$headerName] = $headerValue;
        $request = new ServerRequest('GET', '/foo', $headers);
        $this->assertEquals([$headerValue], $request->getHeader('TestHeader'));
        $this->assertEquals([$headerValue], $request->getHeader('TESTHEADER'));
        $this->assertEquals([$headerValue], $request->getHeader('TeStHeAdEr'));
        $this->assertEquals([$headerValue], $request->getHeader('testheader'));
    }

    public function testHasHeaderIsCaseInsensitive()
    {
        $headerName = 'TestHeader';
        $headerValue = 'TestValue';
        $headers = [];
        $headers[$headerName] = $headerValue;
        $request = new ServerRequest('GET', '/foo', $headers);
        $this->assertTrue($request->hasHeader('TestHeader'));
        $this->assertTrue($request->hasHeader('TESTHEADER'));
        $this->assertTrue($request->hasHeader('TeStHeAdEr'));
        $this->assertTrue($request->hasHeader('testheader'));
    }

    public function testHasHeaderFalseIfNotFound()
    {
        $request = new ServerRequest('GET', '/foo');
        $this->assertFalse($request->hasHeader('TestHeader'));
    }

    public function testGetHeaderInitialHeaderValues()
    {
        $headers = [
            'TestHeader1' => 'val1',
            'TestHeader2' => ['val2'],
            'TestHeader3' => ['val3', 'val4', 'val5'],
        ];
        $request = new ServerRequest('GET', '/foo', $headers);
        foreach ($headers as $key => $value) {
            if (!is_array($value)) {
                $value = [$value];
            }
            $this->assertEquals($value, $request->getHeader($key));
        }
    }

    public function testMultipleAddedHeaderValues()
    {
        $request = $this->requestFactory->createServerRequest('GET', '/foo');
        $headerName = 'TestHeader';
        $headerValues = [];
        foreach (range(1, 3) as $i) {
            $val = "val$i";
            $request = $request->withAddedHeader($headerName, $val);
            $headerValues[] = $val;
        }
        // check if all values are in header
        $this->assertEquals($headerValues, $request->getHeader($headerName));
    }

    public function testGetHeadersPreserveHeaderCase()
    {
        $headerName = 'TestHEAder';
        $headerVal = 'testval';
        $headers = [];
        $headers[$headerName] = [$headerVal];
        $request = new ServerRequest('GET', '/foo', $headers);
        $this->assertEquals($headers, $request->getHeaders());
    }

    public function testGetHeadersWorksIfNoHeaders()
    {
        $request = new ServerRequest('GET', '/foo');
        $this->assertEquals([], $request->getHeaders());
    }


    public function testWithHeaderExistingPreservesMessage()
    {
        $originalValue = 'originalValue';
        $newValue = 'newValue';
        $headers = [
            'TestHeader1' => $originalValue,
            'TestHeader2' => $originalValue,
        ];
        $originalRequest = new ServerRequest('GET', '/foo', $headers);
        $request = $originalRequest->withHeader('TestHeader1', $newValue);
        $expected = $this->arrayValuesToArrays($headers);
        $this->assertEquals($expected, $originalRequest->getHeaders());
    }

    public function testWithHeaderNewPreservesMessage()
    {
        $originalValue = 'originalValue';
        $newValue = 'newValue';
        $headers = [
            'TestHeader1' => $originalValue,
            'TestHeader2' => $originalValue,
        ];
        $originalRequest = new ServerRequest('GET', '/foo', $headers);
        $request = $originalRequest->withHeader('TestHeader3', $newValue);
        $expected = $this->arrayValuesToArrays($headers);
        $this->assertEquals($expected, $originalRequest->getHeaders());
    }

    public function testWithHeaderExistingOverridesHeader()
    {
        $headers = [
            'TestHeader1' => 'value1',
            'TestHeader2' => 'value2',
        ];
        $newHeaderVal = 'value3';
        $originalRequest = new ServerRequest('GET', '/foo', $headers);
        $request = $originalRequest->withHeader('TestHeader1', $newHeaderVal);
        $headers['TestHeader1'] = $newHeaderVal;
        $expected = $this->arrayValuesToArrays($headers);
        $this->assertEquals($expected, $request->getHeaders());
    }

    public function testWithAddedHeaderNewPreservesMessage()
    {
        $originalValue = 'originalValue';
        $newValue = 'newValue';
        $headers = [
            'TestHeader1' => $originalValue,
            'TestHeader2' => $originalValue,
        ];
        $originalRequest = new ServerRequest('GET', '/foo', $headers);
        $request = $originalRequest->withAddedHeader('TestHeader3', $newValue);
        $expected = $this->arrayValuesToArrays($headers);
        $this->assertEquals($expected, $originalRequest->getHeaders());
    }

    public function testWithAddedHeaderExistingPreservesMessage()
    {
        $originalValue = 'originalValue';
        $newValue = 'newValue';
        $headers = [
            'TestHeader1' => $originalValue,
            'TestHeader2' => $originalValue,
        ];
        $originalRequest = new ServerRequest('GET', '/foo', $headers);
        $request = $originalRequest->withAddedHeader('TestHeader1', $newValue);
        $expected = $this->arrayValuesToArrays($headers);
        $this->assertEquals($expected, $originalRequest->getHeaders());
    }



    public function testWithAddedHeaderNewAddsHeader()
    {
        $headers = [
            'TestHeader1' => 'value1',
            'TestHeader2' => 'value2',
        ];
        $newHeaderName = 'TestHeader3';
        $newHeaderVal = 'value3';
        $originalRequest = new ServerRequest('GET', '/foo', $headers);
        $request = $originalRequest->withAddedHeader($newHeaderName, $newHeaderVal);
        $headers[$newHeaderName] = $newHeaderVal;
        $expected = $this->arrayValuesToArrays($headers);
        $this->assertEquals($expected, $request->getHeaders());
    }

    public function testWithAddedHeaderExistingAddsToHeader()
    {
        $headers = [
            'TestHeader1' => 'value1',
            'TestHeader2' => 'value2',
        ];
        $newHeaderVal = 'value3';
        $originalRequest = new ServerRequest('GET', '/foo', $headers);
        $request = $originalRequest->withAddedHeader('TestHeader1', $newHeaderVal);
        $expected = $this->arrayValuesToArrays($headers);
        $expected['TestHeader1'][] = $newHeaderVal;
        $this->assertEquals($expected, $request->getHeaders());
    }

    public function testWithoutHeaderPreservesMessage()
    {
        $originalValue = 'originalValue';
        $newValue = 'newValue';
        $headers = [
            'TestHeader1' => $originalValue,
            'TestHeader2' => $originalValue,
        ];
        $originalRequest = new ServerRequest('GET', '/foo', $headers);
        $request = $originalRequest->withoutHeader('TestHeader1');
        $expected = $this->arrayValuesToArrays($headers);
        $this->assertEquals($expected, $originalRequest->getHeaders());
    }

    public function testWithoutHeaderRemovesHeader()
    {
        $headers = [
            'TestHeader1' => 'value1',
            'TestHeader2' => 'value2',
        ];
        $request = new ServerRequest('GET', '/foo', $headers);
        $request = $request->withoutHeader('TestHeader1');
        unset($headers['TestHeader1']);
        $expected = $this->arrayValuesToArrays($headers);
        $this->assertEquals($expected, $request->getHeaders());
    }


    public function testWithoutHeaderNonExistingWorks()
    {
        $headers = ['headerName' => 'headerValue'];
        $originalRequest = new ServerRequest('GET', '/foo', $headers);
        $request = $originalRequest->withoutHeader('HeaderNotFound');
        $expected = $this->arrayValuesToArrays($headers);
        $this->assertEquals($expected, $request->getHeaders());
    }

    public function testGetBodyReturnsNewStreamIfNotSet()
    {
        $request = new ServerRequest('GET', '/foo');
        $body = $request->getBody();
        $this->assertInstanceOf(StreamInterface::class, $body);
    }

    public function testWithBodyCreatesWithNewBody()
    {
        $request = new ServerRequest('GET', '/foo', [], 'testbody');
        $stream = $this->createMock(StreamInterface::class);
        $request = $request->withBody($stream);
        $body = $request->getBody();
        $this->assertEquals($stream, $body);
    }

    public function testWithBodyPreservesMessage()
    {
        $request = new ServerRequest('GET', '/foo', [], 'testbody');
        $newRequest = $request->withBody($this->createMock(StreamInterface::class));
        $body = $request->getBody();
        $this->assertEquals('testbody', (string) $body);
    }

    // RequestImplementation

    public function testGetRequestTargetPath()
    {
        $request = new ServerRequest('GET', '/foo');
        $this->assertEquals('/foo', $request->getRequestTarget());
    }

    public function testGetRequestTargetPathAndQuery()
    {
        $pathAndQuery = '/foo?a=1&b=2&c=3';
        $request = new ServerRequest('GET', $pathAndQuery);
        $this->assertEquals($pathAndQuery, $request->getRequestTarget());
    }

    public function testWithRequestTargetCreatesWithNewRequestTarget()
    {
        $path = '/foo';
        $request = new ServerRequest('GET', $path);
        $request = $request->withRequestTarget($path);

        $this->assertEquals($path, $request->getRequestTarget());
    }

    public function testWithRequestTargetCreatesWithNewRequestTargetWithQuery()
    {
        $pathAndQuery = '/foo?a=1&b=2&c=3';
        $request = new ServerRequest('GET', $pathAndQuery);
        $request = $request->withRequestTarget($pathAndQuery);

        $this->assertEquals($pathAndQuery, $request->getRequestTarget());
    }

    public function testWithRequestTargetWithQuestionMarkAndEmptyQuery()
    {
        $path = '/foo';
        $request = new ServerRequest('GET', 'test');
        $request = $request->withRequestTarget($path . '?');

        $this->assertEquals($path, $request->getRequestTarget());
    }

    public function testWithRequestTargetPreservesMessage()
    {
        $pathAndQuery = '/foo?a=1&b=2&c=3';
        $request = new ServerRequest('GET', $pathAndQuery);
        $newRequest = $request->withRequestTarget('/new/path?c=d');
        $this->assertEquals($pathAndQuery, $request->getRequestTarget());
    }

    public function testWithMethodCreatesWithNewMethod()
    {
        $request = new ServerRequest('GET', '/foo');
        $request = $request->withMethod('POST');
        $this->assertEquals('POST', $request->getMethod());
    }

    public function testWithMethodPreservesMessage()
    {
        $request = new ServerRequest('GET', '/foo');
        $newRequest = $request->withMethod('POST');
        $this->assertEquals('GET', $request->getMethod());
    }

    public function testWithUriCreatesWithNewUri()
    {
        $path = '/new/path';
        $uri = $this->createMock(Uri::class);
        $uri->method('getPath')->willReturn($path);
        $request = new ServerRequest('GET', '/foo');
        $request = $request->withUri($uri);
        $this->assertEquals($path, $request->getRequestTarget());
    }

    public function testWithUriPreservesMessage()
    {
        $path = '/foo';
        $uri = $this->createMock(Uri::class);
        $uri->method('getPath')->willReturn('/new/path');
        $request = new ServerRequest('GET', $path);
        $newRequest = $request->withUri($uri);
        $this->assertEquals($path, $request->getRequestTarget());
    }

    public function testWithUriWritesHostHeader()
    {
        $path = '/foo';
        $hostValue = 'custom.host.value';
        $uri = $this->createMock(Uri::class);
        $uri->method('getHost')->willReturn('localhost');
        $request = new ServerRequest('GET', $path, ['host' => $hostValue]);
        $request = $request->withUri($uri);
        $this->assertEquals(['localhost'], $request->getHeader('host'));
    }

    public function testWithUriPreservesHostHeader()
    {
        $path = '/foo';
        $hostValue = 'custom.host.value';
        $uri = $this->createMock(Uri::class);
        $uri->method('getHost')->willReturn('localhost');
        $request = new ServerRequest('GET', $path, ['host' => $hostValue]);
        $request = $request->withUri($uri, true);
        $this->assertEquals([$hostValue], $request->getHeader('host'));
    }

    // ServerRequest

    public function testServerRequestSetsHostAndPortHeader()
    {
        $host = 'https://www.horde.org/';
        $port = 6000;
        $uri = $this->createMock(Uri::class);
        $uri->method('getHost')->willReturn($host);
        $uri->method('getPort')->willReturn($port);
        $request = new ServerRequest('GET', $uri);
        $this->assertEquals(["$host:$port"], $request->getHeader('Host'));
    }

    public function testServerRequestSetsHostHeaderNoPort()
    {
        $host = 'https://www.horde.org/';
        $uri = $this->createMock(Uri::class);
        $uri->method('getHost')->willReturn($host);
        $request = new ServerRequest('GET', $uri);
        $this->assertEquals(["$host"], $request->getHeader('Host'));
    }

    public function testGetServerParamsReturnsInitial()
    {
        $serverParams = ['a', 'b', 'c'];
        $request = new ServerRequest('GET', '/foo', [], null, '1.1', $serverParams);
        $this->assertEquals($serverParams, $request->getServerParams());
    }

    public function testWithCookieParamsCreatesWithNewCookies()
    {
        $cookieParams = ['a', 'b', 'c'];
        $request = new ServerRequest('GET', '/foo');
        $request = $request->withCookieParams($cookieParams);
        $this->assertEquals($cookieParams, $request->getCookieParams());
    }

    public function testWithCookieParamsPreservesMessage()
    {
        $cookiesParams = ['a', 'b', 'c'];
        $request = new ServerRequest('GET', '/foo');
        $newRequest = $request->withCookieParams($cookiesParams);
        $this->assertEquals([], $request->getCookieParams());
    }

    public function testWithQueryParamsCreatesWithNewQuery()
    {
        $queryParams = ['a=1', 'b=2', 'c=3'];
        $request = new ServerRequest('GET', '/foo');
        $request = $request->withQueryParams($queryParams);
        $this->assertEquals($queryParams, $request->getQueryParams());
    }

    public function testWithQueryParamsPreservesMessage()
    {
        $queryParams = ['a', 'b', 'c'];
        $request = new ServerRequest('GET', '/foo');
        $newRequest = $request->withQueryParams($queryParams);
        $this->assertEquals([], $request->getQueryParams());
    }

    public function testWithUploadedFilesCreatesWithNewUploadedFiles()
    {
        $files = [$this->createMock(UploadedFileInterface::class)];
        $request = new ServerRequest('GET', '/foo');
        $request = $request->withUploadedFiles($files);
        $this->assertEquals($files, $request->getUploadedFiles());
    }

    public function testWithUploadedFilesPreservesMessage()
    {
        $files = [$this->createMock(UploadedFileInterface::class)];
        $request = new ServerRequest('GET', '/foo');
        $newRequest = $request->withUploadedFiles($files);
        $this->assertEquals([], $request->getUploadedFiles());
    }

    public function testWithParsedBodyCreatesWithNewParsedBody()
    {
        $body = ['a', 'b', 'c'];
        $request = new ServerRequest('GET', '/foo');
        $request = $request->withParsedBody($body);
        $this->assertEquals($body, $request->getParsedBody());
    }

    public function testWithParsedBodyPreservesMessage()
    {
        $body = ['a', 'b', 'c'];
        $request = new ServerRequest('GET', '/foo');
        $newRequest = $request->withParsedBody($body);
        $this->assertNull($request->getParsedBody());
    }

    public function testWithAttributeCreatesWithNewAttribute()
    {
        $request = new ServerRequest('GET', '/foo');
        $request = $request->withAttribute('testAttribute', 'testValue');
        $this->assertEquals('testValue', $request->getAttribute('testAttribute'));
    }

    public function testWithAttributePreservesMessage()
    {
        $request = new ServerRequest('GET', '/foo');
        $newRequest = $request->withAttribute('testAttribute', 'testValue');
        $this->assertNull($request->getAttribute('testAttribute'));
    }

    public function testWithoutAttributeCreatesWithoutAttribute()
    {
        $request = new ServerRequest('GET', '/foo');
        $request = $request->withAttribute('testAttribute', 'testValue');
        $request = $request->withoutAttribute('testAttribute');
        $this->assertNull($request->getAttribute('testAttribute'));
    }

    public function testWithoutAttributePreservesMessage()
    {
        $request = new ServerRequest('GET', '/foo');
        $request = $request->withAttribute('testAttribute', 'testValue');
        $newRequest = $request->withoutAttribute('testAttribute', 'testValue');
        $this->assertEquals('testValue', $request->getAttribute('testAttribute'));
    }

    public function testGetAttributesGetsAllAttributes()
    {
        $attributes = [];
        $request = new ServerRequest('GET', '/foo');
        foreach (range(1, 5) as $i) {
            $request = $request->withAttribute("testAttribute$i", "testValue$i");
            $attributes["testAttribute$i"] = "testValue$i";
        }
        $this->assertEquals($attributes, $request->getAttributes());
    }
}
