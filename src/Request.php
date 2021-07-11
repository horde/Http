<?php
declare(strict_types=1);
namespace Horde\Http;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;


/**
 * A PSR-7 HTTP request message for Horde
 * 
 * The RequestInterface is supposed to be used by a client making a request.
 * When processing an incoming request on the server, use ServerRequest.
 *
 * Per the HTTP specification, this interface includes properties for
 * each of the following:
 *
 * - Protocol version
 * - HTTP method
 * - URI
 * - Headers
 * - Message body
 *
 * During construction, implementations MUST attempt to set the Host header from
 * a provided URI if no Host header is provided.
 *
 * Requests are considered immutable; all methods that might change state MUST
 * be implemented such that they retain the internal state of the current
 * message and return an instance that contains the changed state.
 */
class Request implements RequestInterface
{
    use MessageImplementation;
    use RequestImplementation;

    /**
     * Request Constructor
     * 
     * @param string $method HTTP method
     * @param string|UriInterface $uri URI
     * @param iterable $headers Request headers
     * @param string|resource|StreamInterface|null $body Request body
     * @param string $version Protocol version
     * 
     * @TODO: On PHP 8, use property parameters and union types
     */
    public function __construct(string $method, $uri, iterable $headers = [], $body = null, string $version = '1.1')
    {
        if (is_string($uri)) {
            $uri = new Uri($uri);
        }

        // TODO: validate the method
        $this->method = $method;
        $this->uri = $uri;
        // Set the host header, even if we might overwrite it from $headers
        $host = $uri->getHost();
        $port = $uri->getPort();
        if ($host) {
            if ($port) {
                $this->storeHeader('host', $host . ':' . $port);
            } else {
                $this->storeHeader('host', $host);
            }
        }
        foreach ($headers as $header => $value) {
            $this->storeHeader($header, $value);
        }

        $this->protocolVersion = $version;

        if ($body instanceof RequestInterface) {
            $this->stream = $body;
        } elseif (is_string($body) && $body) {
            $factory = new StreamFactory();
            $this->stream = $factory->createStream($body);
        }
        // If body is null or empty string, it will create an empty stream on access
    }
}