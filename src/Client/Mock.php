<?php

/**
 * Copyright 2007-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (BSD). If you
 * did not receive this file, see http://www.horde.org/licenses/bsd.
 *
 * @author   Chuck Hagenbuch <chuck@horde.org>
 * @author   Gunnar Wrobel <wrobel@pardus.de>
 * @author   Ralf lang <ralf.lang@ralf-lang.de>
 * @category Horde
 * @license  http://www.horde.org/licenses/bsd BSD
 * @package  Http
 */
declare(strict_types=1);

namespace Horde\Http\Client;

use OutOfBoundsException;
use Horde\Http\Response;
use Horde\Http\ResponseFactory;
use Horde\Http\StreamFactory;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * Mock HTTP client object. Derived from the original Mock Http Request.
 *
 * @author    Chuck Hagenbuch <chuck@horde.org>
 * @author    Gunnar Wrobel <wrobel@pardus.de>
 * @category  Horde
 * @copyright 2007-2017 Horde LLC
 * @license   http://www.horde.org/licenses/bsd BSD
 * @package   Http
 */
class Mock implements ClientInterface
{
    /**
     * Mock responses to return.
     *
     * @var array
     */
    protected $responses = [];

    /**
     * Requests received by sendRequest(), in call order.
     *
     * Recorded so tests can assert on the effective URI, method and
     * headers of what the client under test actually sent.
     *
     * @var array<int, RequestInterface>
     */
    protected array $requests = [];

    protected ResponseFactoryInterface $responseFactory;
    protected StreamFactoryInterface $streamFactory;
    protected Options $options;

    public function __construct(?ResponseFactoryInterface $responseFactory = null, ?StreamFactoryInterface $streamFactory = null, ?Options $options = null)
    {
        $this->streamFactory = $streamFactory ?? new StreamFactory();
        $this->responseFactory = $responseFactory ?? new ResponseFactory();
        $this->options = $options ?? new Options();
    }

    /**
     * Set the HTTP response(s) to be returned by this adapter. This overwrites
     * any responses set before.
     *
     * @param ResponseInterface|ResponseInterface[] $responses
     */
    public function setResponse($responses)
    {
        $this->responses = [];
        is_array($responses) ? $this->addResponses($responses) : $this->addResponses([$responses]);
    }

    /**
     * Set the HTTP response(s) to be returned by this adapter as an array Response objects.
     *
     * @param iterable $responses The responses to be added to the stack.
     *
     * @return void
     */
    public function addResponses(iterable $responses): void
    {
        foreach ($responses as $response) {
            $this->responses[] = $response;
        }
    }

    /**
     * Adds a response to the stack of responses.
     *
     * @param string|resource $body    The response body content.
     * @param string          $code    The response code.
     * @param string          $uri     The request uri.
     * @param array           $headers Response headers. This can be one string
     *                                 representing the whole header or an array
     *                                 of strings with one string per header
     *                                 line.
     *
     * @return ResponseInterface The response.
     */
    public function addResponse(
        $body,
        string|int $code = 200,
        string $uri = '',
        array $headers = []
    ): ResponseInterface {
        // TODO: What about the uri?
        if ($body instanceof StreamInterface) {
            $stream = clone($body);
        } elseif (is_string($body)) {
            $stream = $this->streamFactory->createStream($body);
        } else {
            $stream = $this->streamFactory->createStreamFromResource($body);
        }
        $response = $this->responseFactory->createResponse($code)->withBody($stream);
        foreach ($headers as $name => $header) {
            $response = $response->withAddedHeader($name, $header);
        }
        $this->responses[] = $response;
        return $response;
    }

    /**
     * Actually send a request
     */
    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $this->requests[] = $request;
        if (empty($this->responses)) {
            throw new OutOfBoundsException('sendRequest Mock tried to supply a response which was not loaded first');
        }
        if (count($this->responses) > 1) {
            return array_shift($this->responses);
        }
        return $this->responses[0];
    }

    /**
     * Every request received by sendRequest(), in call order.
     *
     * Callers use this to assert on the URI, method and headers of
     * requests the code under test actually made. The requests are
     * the raw PSR-7 objects passed in, unmodified.
     *
     * @return array<int, RequestInterface>
     */
    public function getRequests(): array
    {
        return $this->requests;
    }

    /**
     * Number of requests received by sendRequest().
     */
    public function getRequestCount(): int
    {
        return count($this->requests);
    }

    /**
     * URIs of every request received, in call order, cast to string.
     *
     * @return array<int, string>
     */
    public function getRequestedUrls(): array
    {
        return array_map(fn(RequestInterface $r) => (string) $r->getUri(), $this->requests);
    }

    /**
     * Return the request received at the given zero-based index, or
     * null when no request has been made at that index yet.
     */
    public function getRequest(int $index = 0): ?RequestInterface
    {
        return $this->requests[$index] ?? null;
    }

    /**
     * Discard the recorded request history. Does not touch the
     * queued response stack.
     */
    public function clearRequests(): void
    {
        $this->requests = [];
    }
}
