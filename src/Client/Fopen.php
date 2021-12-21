<?php

/**
 * Copyright 2007-2021 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (BSD). If you
 * did not receive this file, see http://www.horde.org/licenses/bsd.
 *
 * @author   Chuck Hagenbuch <chuck@horde.org>
 * @author   Ralf Lang <lang@b1-systems.de>
 * @category Horde
 * @license  http://www.horde.org/licenses/bsd BSD
 * @package  Http
 */

declare(strict_types=1);

namespace Horde\Http\Client;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Horde\Http\ClientException;
use Horde\Http\Response;
use Horde\Http\ResponseFactory;
use Horde\Http\Constants;

/**
 * Fopen implementation of the Horde HTTP Client
 *
 * Ported from the original Request and Response designs by Chuck Hagenbuch
 */
class Fopen implements ClientInterface
{
    use ParseHeadersTrait;
    private ResponseFactoryInterface $responseFactory;
    private StreamFactoryInterface $streamFactory;
    private Options $options;
    private array $errors = [];

    public function __construct(ResponseFactoryInterface $responseFactory, StreamFactoryInterface $streamFactory, Options $options)
    {
        $this->options = $options;
        $this->streamFactory = $streamFactory;
        $this->responseFactory = $responseFactory;

        if (!ini_get('allow_url_fopen')) {
            throw new ClientException('allow_url_fopen must be enabled');
        }
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $method = $request->getMethod();
        $uri = (string) $request->getUri();
        $headers = $request->getHeaders();
        $data = $request->getBody();

        $opts = ['http' => []];

        // Proxy settings
        $proxyServer = $this->options->getOption('proxyServer');
        if ($proxyServer) {
            $opts['http']['proxy'] = 'tcp://' . $proxyServer;
            $proxyPort = $this->options->getOption('proxyPort');
            if ($proxyPort) {
                $opts['http']['proxy'] .= ':' . $proxyPort;
            }
            $opts['http']['request_fulluri'] = true;
            $proxyUsername = $this->options->getOption('proxyUsername');
            $proxyPassword = $this->options->getOption('proxyPassword');
            if ($proxyUsername && $proxyPassword) {
                // @TODO check $this->proxyAuthenticationScheme
                $headers['Proxy-Authorization'] = 'Basic ' . base64_encode($proxyUsername . ':' . $proxyPassword);
            }
            $proxyType = $this->options->getOption('proxyType');
            if ($proxyType != Constants::PROXY_HTTP) {
                throw new ClientException(sprintf('Proxy type %s not supported by this request type!', $proxyType));
            }
        }
        $username = $this->options->getOption('username');
        $password = $this->options->getOption('password');
        $authenticationScheme = $this->options->getOption('authenticationScheme');
        // Authentication settings
        if ($username) {
            switch ($authenticationScheme) {
                case Constants::AUTH_BASIC:
                case Constants::AUTH_ANY:
                    $headers['Authorization'] = 'Basic ' . base64_encode($username . ':' . $password);
                    break;

                default:
                    throw new ClientException('Unsupported authentication scheme (' . $authenticationScheme . ')');
            }
        }

        // fopen() requires a protocol scheme
        if (parse_url($uri, PHP_URL_SCHEME) === null) {
            $uri = 'http://' . $uri;
        }

        // Concatenate the headers
        $hdr = [];
        foreach ($headers as $header => $value) {
            $hdr[] = $header . ': ' . $value;
        }

        // Stream context config.
        $opts['http']['method'] = $method;
        $opts['http']['header'] = implode("\n", $hdr);
        $opts['http']['content'] = (string) $data;
        $opts['http']['timeout'] = $this->options->getOption('timeout');
        $opts['http']['max_redirects'] = $this->options->getOption('redirects');
        $opts['http']['ignore_errors'] = true;
        $opts['http']['user_agent'] = $this->options->getOption('userAgent');
        $opts['ssl']['verify_peer'] = $this->options->getOption('verifyPeer');
        // Always allow self-signed? Really?
        $opts['ssl']['allow_self_signed'] = true;

        $context = stream_context_create($opts);
        set_error_handler(array($this, 'errorHandler'), E_WARNING | E_NOTICE);
        $streamResource = fopen($uri, 'rb', false, $context);
        restore_error_handler();
        if (!$streamResource) {
            if (
                isset($this->errors[0]['message']) &&
                preg_match('/HTTP\/(\d+\.\d+) (\d{3}) (.*)$/', $this->errors[0]['message'], $matches)
            ) {
                // Create a Response for the HTTP error code
                return new $this->responseFactory->createResponse($matches[0]);
            } else {
                throw new ClientException('Problem with ' . $uri . ': ' . implode('. ', array_reverse($this->errors)));
            }
        }

        $meta = stream_get_meta_data($streamResource);
        $headers = isset($meta['wrapper_data']) ? $meta['wrapper_data'] : [];

        //return new Response
        $headerList = $this->parseHeaders($headers);
        $psrStream = $this->streamFactory->createStreamFromResource($streamResource);
        $response = $this->responseFactory
            ->createResponse($this->parsedCode)
            ->withBody($psrStream);
        if ($this->parsedHttpVersion) {
            $response = $response->withProtocolVersion($this->parsedHttpVersion);
        }
        foreach ($headerList as $name => $value) {
            $response = $response->withAddedHeader($name, $value);
        }
        return $response;
    }
    /**
     * Helper for catching fopen errors, hopefully getting a HTTP error code
     */
    protected function errorHandler($errno, $errstr, $errfile, $errline, $errcontext)
    {
        array_unshift($this->errors, preg_replace('/^(.*?) \[<a href[^\]]*\](.*)/', '$1$2', $errstr));
    }
}
