<?php

/**
 * Copyright 2007-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (BSD). If you
 * did not receive this file, see http://www.horde.org/licenses/bsd.
 *
 * @author   Chuck Hagenbuch <chuck@horde.org>
 * @author   Ralf Lang <ralf.lang@ralf-lang.de>
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

    /**
     * fopen() failure messages captured by errorHandler(), most recent
     * first (unshifted). Each entry is a string of the form emitted by
     * PHP's fopen() warnings.
     *
     * @var list<string>
     */
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

        $opts = ['http' => [], 'ssl' => []];

        // Proxy settings
        $proxyServer = $this->options->getString('proxyServer');
        if ($proxyServer !== null && $proxyServer !== '') {
            $opts['http']['proxy'] = 'tcp://' . $proxyServer;
            $proxyPort = $this->options->getInt('proxyPort');
            if ($proxyPort) {
                $opts['http']['proxy'] .= ':' . $proxyPort;
            }
            $opts['http']['request_fulluri'] = true;
            $proxyUsername = $this->options->getString('proxyUsername');
            $proxyPassword = $this->options->getString('proxyPassword');
            if ($proxyUsername && $proxyPassword) {
                // @TODO check $this->proxyAuthenticationScheme
                $headers['Proxy-Authorization'] = ['Basic ' . base64_encode($proxyUsername . ':' . $proxyPassword)];
            }
            $proxyType = $this->options->getInt('proxyType');
            if ($proxyType !== Constants::PROXY_HTTP) {
                throw new ClientException(sprintf('Proxy type %s not supported by this request type!', (string) $proxyType));
            }
        }
        $username = $this->options->getString('username');
        $password = $this->options->getString('password');
        $authenticationScheme = $this->options->getString('authenticationScheme');
        // Authentication settings
        if ($username !== null && $username !== '') {
            switch ($authenticationScheme) {
                case Constants::AUTH_BASIC:
                case Constants::AUTH_ANY:
                    $headers['Authorization'] = ['Basic ' . base64_encode($username . ':' . (string) $password)];
                    break;

                default:
                    throw new ClientException('Unsupported authentication scheme (' . (string) $authenticationScheme . ')');
            }
        }

        // fopen() requires a protocol scheme
        if (parse_url($uri, PHP_URL_SCHEME) === null) {
            $uri = 'http://' . $uri;
        }

        // Concatenate the headers
        $hdr = [];
        foreach ($headers as $header => $value) {
            $hdr[] = $header . ': ' . implode(', ', $value);
        }

        // Stream context config.
        $opts['http']['method'] = $method;
        $opts['http']['header'] = implode("\n", $hdr);
        $opts['http']['content'] = (string) $data;
        $timeout = $this->options->getInt('timeout');
        if ($timeout !== null) {
            $opts['http']['timeout'] = $timeout;
        }
        $redirects = $this->options->getInt('redirects');
        if ($redirects !== null) {
            $opts['http']['max_redirects'] = $redirects;
        }
        $opts['http']['ignore_errors'] = true;
        $userAgent = $this->options->getString('userAgent');
        if ($userAgent !== null) {
            $opts['http']['user_agent'] = $userAgent;
        }
        $opts['ssl']['verify_peer'] = $this->options->getBool('verifyPeer', true);
        // Always allow self-signed? Really?
        $opts['ssl']['allow_self_signed'] = true;

        $context = stream_context_create($opts);
        set_error_handler([$this, 'errorHandler'], E_WARNING | E_NOTICE);
        $streamResource = fopen($uri, 'rb', false, $context);
        restore_error_handler();
        if (!$streamResource) {
            $matches = [];
            if (
                isset($this->errors[0])
                && preg_match('/HTTP\/(\d+\.\d+) (\d{3}) (.*)$/', $this->errors[0], $matches)
            ) {
                // Create a Response for the HTTP error code
                return $this->responseFactory->createResponse((int) $matches[2]);
            } else {
                throw new ClientException('Problem with ' . $uri . ': ' . implode('. ', array_reverse($this->errors)));
            }
        }

        $meta = stream_get_meta_data($streamResource);
        /** @var list<string> $rawHeaders */
        $rawHeaders = is_array($meta['wrapper_data'] ?? null) ? $meta['wrapper_data'] : [];

        //return new Response
        $headerList = $this->parseHeaders($rawHeaders);
        $psrStream = $this->streamFactory->createStreamFromResource($streamResource);
        $response = $this->responseFactory
            ->createResponse($this->parsedCode)
            ->withBody($psrStream);
        if ($this->parsedHttpVersion !== '') {
            $response = $response->withProtocolVersion($this->parsedHttpVersion);
        }
        foreach ($headerList as $name => $value) {
            // parseHeaders() writes either a string or list<string>; the
            // CaseInsensitiveArray offsetGet has no return-type generics
            // so PHPStan sees mixed. Guard defensively.
            if (!is_string($value) && !is_array($value)) {
                continue;
            }
            /** @var string|array<string> $value */
            $response = $response->withAddedHeader((string) $name, $value);
        }
        return $response;
    }

    /**
     * Helper for catching fopen errors, hopefully getting a HTTP error code.
     *
     * Signature matches set_error_handler(); the return value is ignored
     * (we always want PHP's normal error propagation suppressed).
     *
     * @param int $errno
     * @param string $errstr
     * @param string $errfile
     * @param int $errline
     */
    protected function errorHandler(int $errno, string $errstr, string $errfile = '', int $errline = 0): bool
    {
        $stripped = preg_replace('/^(.*?) \[<a href[^\]]*\](.*)/', '$1$2', $errstr);
        array_unshift($this->errors, $stripped ?? $errstr);
        return true;
    }
}
