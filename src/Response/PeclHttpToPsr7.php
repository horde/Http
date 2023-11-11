<?php
namespace Horde\Http\Response;
use \Psr\Http\Message\ResponseFactoryInterface;
use \Psr\Http\Message\ResponseInterface;
use \Psr\Http\Message\StreamFactoryInterface;
use \Psr\Http\Message\StreamInterface;
use http\Client\Response;
use Horde\Http\ClientException;

/**
 * Convert a pecl/Http native message to a PSR7 response
 * Split off from the PeclHttp Client implementation
 */
trait PeclHttpToPsr7
{
    /**
     * Convert to PSR-7 format
     * 
     * @param http\Client\Response $httpResponse The httpClient response
     * 
     * @return ResponseInterface The PSR-7 equivalent
     */
    private function convertPeclHttpResponseToPsr7(Response $httpResponse
    ): ResponseInterface
    {
        try {
            $info = $httpResponse->getTransferInfo();
        } catch (\http\Exception $e) {
            throw new ClientException($e);
        }
        try {
            $uri = $info->effective_url;
        } catch (\http\Exception\RuntimeException $e) {
            // TODO
        }
        $httpVersion = $httpResponse->getHttpVersion();
        $responseCode = $info->response_code;
        $headers = $httpResponse->getHeaders();
        $bodyResource = $httpResponse->getBody()->getResource(); // We can use body->getResource
        $psr7Stream = $this->streamFactory->createStreamFromResource($bodyResource);
        $psr7Response = $this->responseFactory->createResponse($responseCode);
        $psr7Response = $psr7Response->withProtocolVersion($httpVersion)->withBody($psr7Stream);
        foreach ($headers as $name => $value) {
            $psr7Response = $psr7Response->withHeader($name, $value);
        }
        return $psr7Response;
    }
}