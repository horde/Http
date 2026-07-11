<?php

/**
 * Copyright 2007-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (BSD). If you
 * did not receive this file, see http://www.horde.org/licenses/bsd.
 *
 * @author   Chuck Hagenbuch <chuck@horde.org>
 * @category Horde
 * @license  http://www.horde.org/licenses/bsd BSD
 * @package  Http
 */

namespace Horde\Http;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Horde_Url;

/**
 * Wrap a PSR-18 HTTP client into a frontend similar to the Horde_Http_Client.
 *
 * Also expose the PSR-18 interface
 *
 * @author         Chuck Hagenbuch <chuck@horde.org>
 * @category       Horde
 * @copyright      2007-2017 Horde LLC
 * @license        http://www.horde.org/licenses/bsd BSD
 * @package        Http
 * @property       boolean      $httpMethodOverride
 *                              @see $_httpMethodOverride
 * @property-write string|Horde_Url $request.uri
 *                              Default URI if not specified for individual
 *                              requests.
 * @property-write array        $request.headers
 *                              Hash with additional request headers.
 * @property-write string       $request.method
 *                              Default request method if not specified for
 *                              individual requests.
 * @property-write array|string $request.data
 *                              POST data fields or POST/PUT data body.
 * @property-write string       $request.username
 *                              Authentication user name.
 * @property-write string       $request.password
 *                              Authentication password.
 * @property-write string       $request.authenticationScheme
 *                              Authentication method, one of the
 *                              Horde_Http::AUTH_* constants.
 * @property-write string       $request.proxyServer
 *                              Host name of a proxy server.
 * @property-write integer      $request.proxyPort
 *                              Port number of a proxy server.
 * @property-write integer      $request.proxyType
 *                              Proxy server type, one of the
 *                              Horde_Http::PROXY_* constants.
 * @property-write string       $request.proxyUsername
 *                              Proxy authentication user name.
 * @property-write string       $request.proxyPassword
 *                              Proxy authentication password.
 * @property-write string       $request.proxyAuthenticationScheme
 *                              Proxy authentication method, one of the
 *                              Horde_Http::AUTH_* constants.
 * @property-write integer      $request.redirects
 *                              Maximum number of redirects to follow.
 * @property-write integer      $request.timeout
 *                              Timeout in seconds.
 * @property-write boolean      $request.verifyPeer
 *                              Verify SSL peer certificates?
 */
class HordeClientWrapper implements ClientInterface
{
    protected RequestFactoryInterface $requestFactory;
    protected StreamFactoryInterface $streamFactory;

    /**
     * The previous HTTP request, or null when no request has been sent yet.
     */
    protected ?RequestInterface $lastRequest = null;

    /**
     * The most recent HTTP response, or null when no request has been sent yet.
     */
    protected ?ResponseInterface $lastResponse = null;

    /**
     * Use POST instead of PUT and DELETE, sending X-HTTP-Method-Override with
     * the intended method name instead.
     */
    protected bool $httpMethodOverride = false;

    /**
     * A predefined uri for generated requests.
     */
    public ?string $uri = null;

    public ClientInterface $client;

    /**
     * HordeClientWrapper constructor.
     *
     * Use a factory to run this.
     * TODO: Move Client and $httpMethodOverride to an options container;
     * TODO: PHP 8: Use property parameters
     */
    public function __construct(
        ClientInterface $client,
        RequestFactoryInterface $requestFactory,
        StreamFactoryInterface $streamFactory,
        bool $httpMethodOverride = false
    ) {
        $this->requestFactory = $requestFactory;
        $this->streamFactory = $streamFactory;
        $this->client = $client;
        $this->httpMethodOverride = $httpMethodOverride;
    }

    /**
     * Sends a GET request.
     *
     * @param string|Uri|Horde_Url|null $uri Request URI, or null to use $this->uri.
     * @param array<string, string|list<string>> $headers Additional request headers.
     *
     * @throws ClientException
     * @return ResponseInterface
     */
    public function get($uri = null, array $headers = []): ResponseInterface
    {
        return $this->request('GET', $uri, null, $headers);
    }

    /**
     * Sends a POST request.
     *
     * @param string|Uri|Horde_Url|null $uri Request URI, or null to use $this->uri.
     * @param array<string, mixed>|string|null $data Data fields or data body.
     * @param array<string, string|list<string>> $headers Additional request headers.
     *
     * @throws Exception
     * @return ResponseInterface
     */
    public function post($uri = null, $data = null, array $headers = []): ResponseInterface
    {
        return $this->request('POST', $uri, $data, $headers);
    }

    /**
     * Sends a PUT request.
     *
     * @param string|Uri|Horde_Url|null $uri Request URI, or null to use $this->uri.
     * @param string|null $data Data body.
     * @param array<string, string|list<string>> $headers Additional request headers.
     *
     * @throws Exception
     * @return ResponseInterface
     */
    public function put($uri = null, $data = null, array $headers = []): ResponseInterface
    {
        if ($this->httpMethodOverride) {
            $headers = array_merge(
                ['X-HTTP-Method-Override' => 'PUT'],
                $headers
            );
            return $this->post($uri, $data, $headers);
        }

        return $this->request('PUT', $uri, $data, $headers);
    }

    /**
     * Sends a DELETE request.
     *
     * @param string|Uri|Horde_Url|null $uri Request URI, or null to use $this->uri.
     * @param array<string, string|list<string>> $headers Additional request headers.
     *
     * @throws Exception
     * @return ResponseInterface
     */
    public function delete($uri = null, array $headers = []): ResponseInterface
    {
        if ($this->httpMethodOverride) {
            $headers = array_merge(
                ['X-HTTP-Method-Override' => 'DELETE'],
                $headers
            );
            return $this->post($uri, null, $headers);
        }

        return $this->request('DELETE', $uri, null, $headers);
    }

    /**
     * Sends a HEAD request.
     *
     * @param string|Uri|Horde_Url|null $uri Request URI, or null to use $this->uri.
     * @param array<string, string|list<string>> $headers Additional request headers.
     *
     * @throws Exception
     * @return ResponseInterface
     */
    public function head($uri = null, array $headers = []): ResponseInterface
    {
        return $this->request('HEAD', $uri, null, $headers);
    }

    /**
     * Builds and sends a HTTP request (H5 style).
     *
     * @param string $method HTTP request method (GET, PUT, etc.).
     * @param string|Uri|Horde_Url|null $uri URI to request, if different from $this->uri.
     * @param array<string, mixed>|string|null $data Request data. Array of form data that will
     *                               be encoded automatically, or a raw string.
     * @param iterable<string, string|list<string>> $headers Any headers specific to this
     *                               request. They will be combined with $this->_headers, and
     *                               override headers of the same name for this request only.
     *
     * @throws ClientException
     * @return ResponseInterface
     */
    public function request(
        string $method,
        $uri = null,
        $data = null,
        iterable $headers = []
    ): ResponseInterface {
        if ($uri === null || $uri === '') {
            $uri = $this->uri;
        }
        if ($uri === null || $uri === '') {
            throw new ClientException('Cannot send a request without a URI');
        }

        // The PSR-17 factory requires string or UriInterface; coerce a
        // Horde_Url or an Http\Uri to string so the boundary is sharp.
        if ($uri instanceof Horde_Url) {
            $uri = (string) $uri;
        }

        // Build a request
        $request = $this->requestFactory->createRequest($method, $uri);
        foreach ($headers as $name => $header) {
            $request = $request->withHeader($name, $header);
        }

        // Send
        return $this->sendRequest($request);
    }


    /**
     * Sends a PSR-7 request and returns a PSR-7 response.
     *
     * @param RequestInterface $request
     *
     * @return ResponseInterface
     *
     * @throws \Psr\Http\Client\ClientExceptionInterface If an error happens while processing the request.
     */
    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        // TODO: Handle method override here
        $this->lastRequest = $request;
        $this->lastResponse = $this->client->sendRequest($request);
        return $this->lastResponse;
    }
}
