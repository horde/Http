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
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Horde\Http\Constants;
use Horde\Http\ClientException;
use Horde\Http\NetworkException;
use Horde\Http\Response;
use Horde\Http\ResponseFactory;
use Horde\Http\StreamFactory;

/**
 * HTTP client for the curl backend.
 *
 * Adapted from the original Request/Response implementation.
 */
class Curl implements ClientInterface
{
    use ParseHeadersTrait;
    private ResponseFactoryInterface $responseFactory;
    /**
     * Accepted for API parity with other clients (Fopen, Mock). Curl's
     * body flows through curl itself rather than a stream factory, so
     * this field is unused by sendRequest() — kept to avoid a
     * constructor signature break.
     * @phpstan-ignore property.onlyWritten
     */
    private StreamFactoryInterface $streamFactory;
    private Options $options;
    /**
     * Map of HTTP authentication schemes from Horde_Http constants to
     * HTTP_AUTH constants.
     *
     * @var array<string, int>
     */
    private const HTTP_AUTH_SCHEMES = [
        Constants::AUTH_ANY => CURLAUTH_ANY,
        Constants::AUTH_BASIC => CURLAUTH_BASIC,
        Constants::AUTH_DIGEST => CURLAUTH_DIGEST,
        Constants::AUTH_GSSNEGOTIATE => CURLAUTH_GSSNEGOTIATE,
        Constants::AUTH_NTLM => CURLAUTH_NTLM,
    ];

    public function __construct(ResponseFactoryInterface $responseFactory, StreamFactoryInterface $streamFactory, Options $options)
    {
        if (!extension_loaded('curl')) {
            throw new ClientException('The curl extension is not installed. See http://php.net/curl.installation');
        }
        $this->responseFactory = $responseFactory;
        $this->streamFactory = $streamFactory;
        $this->options = $options;
        // Configure curl from options
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        // Parse request object and client settings into curl request parameters
        $curl = curl_init();
        if ($curl === false) {
            throw new ClientException('Failed to initialise curl session');
        }
        $uri = (string) $request->getUri();
        if ($uri === '') {
            throw new ClientException('Cannot send a request with an empty URI');
        }
        curl_setopt($curl, CURLOPT_URL, $uri);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HEADER, true);
        $method = $request->getMethod();
        if ($method === '') {
            throw new ClientException('Cannot send a request with an empty method');
        }
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
        $timeout = $this->options->getInt('timeout');
        if ($timeout !== null) {
            curl_setopt($curl, CURLOPT_TIMEOUT, $timeout);
        }
        $verifyPeer = $this->options->getBool('verifyPeer', true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, $verifyPeer);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, $verifyPeer ? 2 : 0);

        // User-Agent
        $userAgent = $this->options->getString('userAgent');
        if ($userAgent !== null && $userAgent !== '') {
            curl_setopt($curl, CURLOPT_USERAGENT, $userAgent);
        }

        // Redirects
        $redirects = $this->options->getInt('redirects');
        if ($redirects) {
            curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($curl, CURLOPT_MAXREDIRS, $redirects);
        } else {
            curl_setopt($curl, CURLOPT_FOLLOWLOCATION, false);
            curl_setopt($curl, CURLOPT_MAXREDIRS, 0);
        }

        // Proxy settings
        $proxyServer = $this->options->getString('proxyServer');
        if ($proxyServer !== null && $proxyServer !== '') {
            curl_setopt($curl, CURLOPT_PROXY, $proxyServer);
            $proxyPort = $this->options->getInt('proxyPort');
            if ($proxyPort) {
                curl_setopt($curl, CURLOPT_PROXYPORT, $proxyPort);
            }

            $proxyUsername = $this->options->getString('proxyUsername');
            $proxyPassword = $this->options->getString('proxyPassword');
            $proxyAuthenticationScheme = $this->options->getString('proxyAuthenticationScheme');

            if ($proxyUsername && $proxyPassword) {
                curl_setopt($curl, CURLOPT_PROXYUSERPWD, $proxyUsername . ':' . $proxyPassword);
                if ($proxyAuthenticationScheme !== null) {
                    curl_setopt($curl, CURLOPT_PROXYAUTH, $this->httpAuthScheme($proxyAuthenticationScheme));
                }
            }

            $proxyType = $this->options->getInt('proxyType');
            if ($proxyType === Constants::PROXY_SOCKS5) {
                curl_setopt($curl, CURLOPT_PROXYTYPE, CURLPROXY_SOCKS5);
            } elseif ($proxyType !== Constants::PROXY_HTTP) {
                throw new ClientException(sprintf('Proxy type %s not supported by this request type!', (string) $proxyType));
            }
        }

        // Authentication settings - Shouldn't we leave these to the request's header handling?
        $username = $this->options->getString('username');
        $password = $this->options->getString('password');
        $authenticationScheme = $this->options->getString('authenticationScheme');

        if ($username !== null && $username !== '') {
            curl_setopt($curl, CURLOPT_USERPWD, $username . ':' . (string) $password);
            if ($authenticationScheme !== null) {
                curl_setopt($curl, CURLOPT_HTTPAUTH, $this->httpAuthScheme($authenticationScheme));
            }
        }

        // Concatenate the headers
        $headers = $request->getHeaders();
        // Why is that?
        if (empty($headers['Expect'])) {
            $headers['Expect'] = [''];
        }
        $headerLines = [];
        foreach (array_keys($headers) as $headerKey) {
            $headerLines[] = $headerKey . ': ' . $request->getHeaderLine($headerKey);
        }

        curl_setopt($curl, CURLOPT_HTTPHEADER, $headerLines);

        $body = $request->getBody();
        $bodyStr = (string) $body;
        if ($bodyStr !== '') {
            curl_setopt($curl, CURLOPT_POSTFIELDS, $bodyStr);
        }

        $result = curl_exec($curl);
        if ($result === false) {
            throw new ClientException(curl_error($curl), curl_errno($curl));
        }
        if ($result === true) {
            // Only happens when CURLOPT_RETURNTRANSFER is off, which we
            // set to true above. Defensive: satisfy PHPStan's true|string
            // narrowing without adding a silent misbehaviour.
            throw new ClientException('curl_exec unexpectedly returned true; RETURNTRANSFER was set');
        }
        $info = curl_getinfo($curl);
        // Process curl answer into response

        // There's most likely a more modern way to separate headers from body

        /* Curl returns multiple headers, if the last action required multiple
         * requests, e.g. when doing Digest authentication. Only parse the
         * headers of the latest response. */
        $matches = [];
        preg_match_all('/(^|\r\n\r\n)(HTTP\/)/', $result, $matches, PREG_OFFSET_CAPTURE);
        $startOfHeaders = (int) $matches[2][count($matches[2]) - 1][1];
        $endOfHeaders = strpos($result, "\r\n\r\n", $startOfHeaders);
        if ($endOfHeaders === false) {
            throw new ClientException('Malformed HTTP response: no header/body delimiter');
        }
        $headers = substr($result, $startOfHeaders, $endOfHeaders - $startOfHeaders);


        $headerList = $this->parseHeaders($headers);
        $body = substr($result, $endOfHeaders + 4);

        $code = (int) $info['http_code'];


        $response = $this->responseFactory->createResponse($code);
        foreach ($headerList as $name => $value) {
            if (!is_string($value) && !is_array($value)) {
                continue;
            }
            /** @var string|array<string> $value */
            $response = $response->withAddedHeader((string) $name, $value);
        }

        $response->getBody()->write($body);
        $response->getBody()->seek(0);

        return $response;
    }

    /**
     * Translate Constants::AUTH_* constant to CURLAUTH_*
     *
     * @param string $httpAuthScheme
     * @throws ClientException
     * @return int
     */
    protected function httpAuthScheme(string $httpAuthScheme): int
    {
        if (!isset(self::HTTP_AUTH_SCHEMES[$httpAuthScheme])) {
            throw new ClientException('Unsupported authentication scheme (' . $httpAuthScheme . ')');
        }
        return self::HTTP_AUTH_SCHEMES[$httpAuthScheme];
    }
}
