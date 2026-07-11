<?php

namespace Horde\Http\Response;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;
use http\Client\Response as PeclHttpResponse;
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
     * @param PeclHttpResponse $httpResponse The httpClient response
     *
     * @return ResponseInterface The PSR-7 equivalent
     */
    private function convertPeclHttpResponseToPsr7(
        PeclHttpResponse $httpResponse
    ): ResponseInterface {
        $info = $httpResponse->getTransferInfo();
        if (!is_object($info)) {
            throw new ClientException('pecl_http returned unexpected transfer info shape');
        }
        /** @var object{effective_url: string, response_code: int} $info */
        $uri = $info->effective_url;
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
