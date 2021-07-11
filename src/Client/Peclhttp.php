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

use Horde\Http\ClientException;
use Horde\Http\Response;
use Horde\Http\ResponseFactory;
use Horde\Http\StreamFactory;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Client\ClientExceptionInterface;
/**
 * HTTP client for the pecl_http 1.0 extension 
 * 
 * Ported from the original Request/Response implementation
 */
class PeclHttp
{
    protected $_httpAuthSchemes = array(
        Horde_Http::AUTH_ANY => HTTP_AUTH_ANY,
        Horde_Http::AUTH_BASIC => HTTP_AUTH_BASIC,
        Horde_Http::AUTH_DIGEST => HTTP_AUTH_DIGEST,
        Horde_Http::AUTH_GSSNEGOTIATE => HTTP_AUTH_GSSNEG,
        Horde_Http::AUTH_NTLM => HTTP_AUTH_NTLM,
    );

    public function __construct(ResponseFactoryInterface $responseFactory, StreamFactoryInterface $streamFactory, Options $options)
    {
        if (!class_exists('HttpRequest', false)) {
            throw new ClientException('The pecl_http extension is not installed. See http://php.net/http.install');
        }
        $this->responseFactory = $responseFactory;
        $this->streamFactory = $streamFactory;
        $this->options = $options;
        // Configure curl from options
    }


}