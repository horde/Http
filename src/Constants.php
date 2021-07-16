<?php
/**
 * Copyright 2007-2017 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (BSD). If you
 * did not receive this file, see http://www.horde.org/licenses/bsd.
 *
 * @author   Chuck Hagenbuch <chuck@horde.org>
 * @category Horde
 * @license  http://www.horde.org/licenses/bsd BSD
 * @package  Http
 */
declare(strict_types=1);
namespace Horde\Http;
/**
 * Constants for Horde_Http.
 *
 * @author    Chuck Hagenbuch <chuck@horde.org>
 * @category  Horde
 * @copyright 2007-2017 Horde LLC
 * @license   http://www.horde.org/licenses/bsd BSD
 * @package   Http
 */
class Constants
{
    /**
     * Authentication schemes
     */
    public const AUTH_ANY = 'ANY';
    public const AUTH_BASIC = 'BASIC';
    public const AUTH_DIGEST = 'DIGEST';
    public const AUTH_NTLM = 'NTLM';
    public const AUTH_GSSNEGOTIATE = 'GSSNEGOTIATE';

    /**
     * Proxy types
     */
    public const PROXY_HTTP = 0;
    public const PROXY_SOCKS4 = 1;
    public const PROXY_SOCKS5 = 2;

    /**
     * Uri SCHEMATA and their ports
     */
    public const URI_SCHEMATA = [
        'ftp' => 21,
        'http' => 80,
        'https' => 443
    ];


    /**
     * Stream modes we can read from
     */
    public const READABLE_STREAM_MODES = [
        'a+', 'c+', 'r','r+', 'w+', 'x+',
        'a+b', 'c+b', 'rb','r+b', 'w+b', 'x+b',
        'a+t', 'c+t', 'rt','r+t', 'w+t', 'x+t',
        
    ];

    /**
     * Stream modes that can be written to
     * 
     * @const WRITABLE_STREAM_MODES string[] Modes as reported by stream_get_meta
     */
    public const WRITABLE_STREAM_MODES = [
        'a', 'a+', 'w', 'w+', 'r+', 'rw', 'x+', 'c+',
        'wb', 'w+b', 'r+b', 'x+b', 'c+b', 
        'w+t', 'r+t', 'x+t', 'c+t'
    ];

    /**
     * Collection of reason phrases for HTTP status codes
     * as suggested by RFC 7231 or IANA, keyed by status code
     */
    public const REASON_PHRASES = [
        100 => 'Continue',
        101 => 'Switching Protocols',
        102 => 'Processing',
        200 => 'OK',
        201 => 'Created',
        202 => 'Accepted',
        203 => 'Non-Authoritative Information',
        204 => 'No Content',
        205 => 'Reset Content',
        206 => 'Partial Content',
        207 => 'Multi-status',
        208 => 'Already Reported',
        300 => 'Multiple Choices',
        301 => 'Moved Permanently',
        302 => 'Found',
        303 => 'See Other',
        304 => 'Not Modified',
        305 => 'Use Proxy',
        306 => 'Switch Proxy',
        307 => 'Temporary Redirect',
        308 => 'Permanent Redirect',
        400 => 'Bad Request',
        401 => 'Unauthorized',
        402 => 'Payment Required',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        406 => 'Not Acceptable',
        407 => 'Proxy Authentication Required',
        408 => 'Request Time-out',
        409 => 'Conflict',
        410 => 'Gone',
        411 => 'Length Required',
        412 => 'Precondition Failed',
        413 => 'Request Entity Too Large',
        414 => 'Request-URI Too Large',
        415 => 'Unsupported Media Type',
        416 => 'Requested range not satisfiable',
        417 => 'Expectation Failed',
        418 => 'I\'m a teapot',
        422 => 'Unprocessable Entity',
        423 => 'Locked',
        424 => 'Failed Dependency',
        425 => 'Unordered Collection',
        426 => 'Upgrade Required',
        428 => 'Precondition Required',
        429 => 'Too Many Requests',
        431 => 'Request Header Fields Too Large',
        451 => 'Unavailable For Legal Reasons',
        500 => 'Internal Server Error',
        501 => 'Not Implemented',
        502 => 'Bad Gateway',
        503 => 'Service Unavailable',
        504 => 'Gateway Time-out',
        505 => 'HTTP Version not supported',
        506 => 'Variant Also Negotiates',
        507 => 'Insufficient Storage',
        508 => 'Loop Detected',
        510 => 'Not Extended',
        511 => 'Network Authentication Required',
    ];
}