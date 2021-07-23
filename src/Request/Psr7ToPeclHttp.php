<?php
namespace Horde\Http\Request;
use \Psr\Http\Message\RequestInterface as Psr7Request;
use \Psr\Http\Message\StreamInterface as Psr7Stream;
/**
 * Convert a PSR-7 request message to a pecl/Http native message
 * Split off from the PeclHttp Client implementation
 */
trait Psr7ToPeclHttp
{
    /**
     * Convert to native format
     * 
     * @param Psr7Request $request The PSR request to convert
     * 
     * @return \http\Client\Request
     */
    private function convertPsr7RequestToPeclHttp(Psr7Request $request): \http\Client\Request
    {
        $extHttpReqBody = new \http\Message\Body();
        // Mind: Do we need to support special formEncoding? The PSR request should return the correct content anyway.
        /* if (is_array($data)) {
            $body->addForm($data);
        } else {
            $body->append($data);
        }*/
        // TODO: getResource and write reasonable buffer sizes to limit memory footprint
        $extHttpReqBody->append((string) $request->getBody());
        // The extHttp headers format is incompatible with PSR getHeaders format.
        $extHttpReqHeaders = [];
        foreach ($request->getHeaders() as $name => $values) {
            $extHttpReqHeaders[$name] = $request->getHeaderLine($name);
        }
        $extHttpRequest = new \http\Client\Request(
            $request->getMethod(), 
            (string) $request->getUri(),
            $extHttpReqHeaders,
            $extHttpReqBody
        );
        return $extHttpRequest;
    }
}