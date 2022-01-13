<?php

namespace Horde\Http\Test;

use Phpunit\Framework\TestCase;
use Horde\Http\Response;
use Psr\Http\Message\ResponseInterface;
use InvalidArgumentException;

class ResponseTest extends TestCase
{
    public function setUp(): void
    {
    }

    public function testDefaultStatusIs200()
    {
        $response = new Response();
        $status = $response->getStatusCode();
        $this -> assertSame(200, $status);
    }

    public function testExceptionWhenUnkownStatusCode()
    {
        $response = new Response();
        $this -> expectException(InvalidArgumentException::class);
        $response->withStatus(999);

    }

    public function testStatusChangesAccordingly()
    {
        $response = new Response(418);
        $status = $response->getStatusCode();
        $this -> assertSame(418, $status);
    }

    public function testReasonPhraseIsDefault()
    {
        $response = new Response(418,[],null,'1.1');
        $responsePhrase = $response->getReasonPhrase();
        $this -> assertSame("I'm a teapot", $responsePhrase);
    }

    public function testReasonPhraseIsPrioritised()
    {
        $response = new Response(418,[],null,'1.1',"I'm a coffeepot");
        $responsePhrase = $response->getReasonPhrase();
        $this -> assertSame("I'm a coffeepot", $responsePhrase);
    }

    public function testwithStatusReturnsAccordinglyToRFC7231()
    {
        $response = new Response();
        $responsePhrase = $response->withStatus(418)->getReasonPhrase();
        $this -> assertSame("I'm a teapot", $responsePhrase);
    }

    public function testwithStatusPrioritisedSpecificResponsePhrase()
    {
        $response = new Response();
        $responsePhrase = $response->withStatus(418, 'test me if you can')->getReasonPhrase();
        $this -> assertSame('test me if you can', $responsePhrase);
    }

    public function testOriginalResponseNotModifiedByWithStatusMethod()
    {
        $response = new Response(418);
        $response->withStatus(418, 'test me if you can')->getReasonPhrase();
        $originalPhrase = $response->getReasonPhrase();
        $this -> assertSame("I'm a teapot", $originalPhrase);
    }

    public function testOriginalResponseCodeIsNotModifiedByWithStatusMethod()
    {
        $response = new Response(200);
        $response->withStatus(418, 'test me if you can')->getReasonPhrase();
        $originalCode = $response->getStatusCode();
        $this -> assertSame(200, $originalCode);
    }
}
