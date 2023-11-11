<?php
/**
 * Copyright 2007-2021 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (BSD). If you
 * did not receive this file, see http://www.horde.org/licenses/bsd.
 *
 * @author   Chuck Hagenbuch <chuck@horde.org>
 * @author   Gunnar Wrobel <wrobel@pardus.de>
 * @author   Ralf lang <lang@b1-systems.de>
 * @category Horde
 * @license  http://www.horde.org/licenses/bsd BSD
 * @package  Http
 */
declare(strict_types=1);
namespace Horde\Http\Client;
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
    protected ResponseFactoryInterface $responseFactory;
    protected StreamFactoryInterface $streamFactory;
    protected Options $options;

    public function __construct(ResponseFactoryInterface $responseFactory = null, StreamFactoryInterface $streamFactory = null, Options $options = null)
    {
        $this->streamFactory = $streamFactory ?? new StreamFactory();
        $this->responseFactory = $responseFactory ?? new ResponseFactory();
        $this->options = $options;

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
     * @param string|resourse $body    The response body content.
     * @param string          $code    The response code.
     * @param string          $uri     The request uri.
     * @param array           $headers Response headers. This can be one string
     *                                 representing the whole header or an array
     *                                 of strings with one string per header
     *                                 line.
     *
     * @return Response The response.
     */
    public function addResponse(
        $body, $code = 200, $uri = '', $headers = []
    )
    {
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
        if (empty($this->responses)) {
            return null;
        }
        if (count($this->responses) > 1) {
            return array_shift($this->responses);
        }
        return $this->responses[0];        
    }
}
