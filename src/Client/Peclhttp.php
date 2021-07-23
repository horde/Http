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

use Horde\Http\Constants;
use Horde\Http\ClientException;
use Horde\Http\Request\Psr7ToPeclHttp;
use Horde\Http\Response;
use Horde\Http\Response\PeclHttpToPsr7;
use Horde\Http\ResponseFactory;
use Horde\Http\StreamFactory;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Client\ClientExceptionInterface;
use \Horde_Support_CaseInsensitiveArray;
/**
 * HTTP client for the pecl_http extension 
 * 
 * Driver is suitable for PeclHttp 3.x (PHP7) and 4.x (PHP 8) extension versions
 * 
 * PeclHttp used to be the name for the 1.x backend but this is no longer supported
 *
 * Ported from the original Request/Response implementation
 */
class PeclHttp implements ClientInterface
{
    use Psr7ToPeclHttp;
    use PeclHttpToPsr7;
    /**
     * Map of HTTP authentication schemes from Horde_Http constants to
     * implementation specific constants.
     *
     * @var array
     */
    protected $httpAuthSchemes = [
        Constants::AUTH_ANY => \http\Client\Curl\AUTH_ANY,
        Constants::AUTH_BASIC => \http\Client\Curl\AUTH_BASIC,
        Constants::AUTH_DIGEST => \http\Client\Curl\AUTH_DIGEST,
        Constants::AUTH_GSSNEGOTIATE => \http\Client\Curl\AUTH_GSSNEG,
        Constants::AUTH_NTLM => \http\Client\Curl\AUTH_NTLM,
    ];

    /**
     * Map of proxy types from Horde_Http to implementation specific constants.
     *
     * @var array
     */
    protected $proxyTypes = [
        Constants::PROXY_SOCKS4 => \http\Client\Curl\PROXY_SOCKS4,
        Constants::PROXY_SOCKS5 => \http\Client\Curl\PROXY_SOCKS5
    ];
    protected StreamFactoryInterface $streamFactory;
    protected ResponseFactoryInterface $responseFactory;
    protected Options $options;

    /**
     * Translates a Horde_Http::AUTH_* constant to implementation specific
     * constants.
     *
     * @param string $httpAuthScheme  A Horde_Http::AUTH_* constant.
     *
     * @return const An implementation specific authentication scheme constant.
     * @throws ClientException
     */
    protected function httpAuthScheme($httpAuthScheme)
    {
        if (!isset($this->httpAuthSchemes[$httpAuthScheme])) {
            throw new ClientException('Unsupported authentication scheme (' . $httpAuthScheme . ')');
        }
        return $this->httpAuthSchemes[$httpAuthScheme];
    }

    /**
     * Translates a Horde_Http::PROXY_* constant to implementation specific
     * constants.
     *
     * @return const
     * @throws ClientException
     */
    protected function proxyType()
    {
        $proxyType = $this->proxyType;
        if (!isset($this->proxyTypes[$proxyType])) {
            throw new ClientException('Unsupported proxy type (' . $proxyType . ')');
        }
        return $this->proxyTypes[$proxyType];
    }

    /**
     * Generates the HTTP options for the request.
     *
     * @return array array with options
     * @throws Horde_Http_Exception
     */
    protected function httpOptions()
    {
        // Set options
        $httpOptions = [
            'headers' => $this->headers,
            'redirect' => (int)$this->options->redirects,
            'ssl' => [
                'verifypeer' => $this->options->verifyPeer,
                'verifyhost' => $this->options->verifyPeer
            ],
            'timeout' => $this->options->timeout,
            'useragent' => $this->options->userAgent
        ];

        // Proxy settings
        if ($this->options->proxyServer) {
            $httpOptions['proxyhost'] = $this->options->proxyServer;
            if ($this->options->proxyPort) {
                $httpOptions['proxyport'] = $this->options->proxyPort;
            }
            if ($this->options->proxyUsername && $this->options->proxyPassword) {
                $httpOptions['proxyauth'] = $this->options->proxyUsername . ':' . $this->options->proxyPassword;
                $httpOptions['proxyauthtype'] = $this->httpAuthScheme($this->options->proxyAuthenticationScheme);
            }
            if ($this->proxyType == Constants::PROXY_SOCKS4 || $this->proxyType == Constants::PROXY_SOCKS5) {
                $httpOptions['proxytype'] = $this->proxyType();
            } else if ($this->options->proxyType != Constants::PROXY_HTTP) {
                throw new ClientException(sprintf('Proxy type %s not supported by this request type!', $this->options->proxyType));
            }
        }

        // Authentication settings
        if ($this->options->username) {
            $httpOptions['httpauth'] = $this->options->username . ':' . $this->options->password;
            $httpOptions['httpauthtype'] = $this->httpAuthScheme($this->options->authenticationScheme);
        }

        return $httpOptions;
    }

    public function __construct(ResponseFactoryInterface $responseFactory, StreamFactoryInterface $streamFactory, Options $options)
    {
        if (!class_exists('\http\Client', false)) {
            throw new ClientException('The pecl_http extension is not installed. See http://php.net/http.install');
        }
        $this->responseFactory = $responseFactory;
        $this->streamFactory = $streamFactory;
        $this->options = $options;
        // Configure curl from options
    }

    /**
     * Send this HTTP request
     *
     * @throws ClientException
     * @return Horde_Http_Response_Base
     */
    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        // First transform a PSR-7 request to a pecl/Http request
        $extHttpRequest = $this->convertPsr7RequestToPeclHttp($request);

        // at this time only the curl driver is supported
        $client = new \http\Client('curl');
        $client->setOptions($this->httpOptions());
        $client->enqueue($extHttpRequest);

        try {
            $client->send();
            $httpResponse = $client->getResponse($extHttpRequest);
        } catch (\http\Exception $e) {
            throw new ClientException($e);
        }
        // Convert the pecl/Http response into a psr-7 response
        $psr7Response = $this->convertPeclHttpResponseToPsr7($httpResponse);
        return $psr7Response;
    }
}