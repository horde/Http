<?php
/**
 * Copyright 2020-2021 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (BSD). If you
 * did not receive this file, see http://www.horde.org/licenses/bsd.
 *
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
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Horde\Http\Constants;

/**
 * An Options container for HTTP clients
 */
class Options
{
    protected $options = [
        'username' => null,
        'password' => null,
        'authenticationScheme' => null,
        'proxyServer' => null,
        'proxyPort' => null,
        'proxyType' => Constants::PROXY_HTTP,
        'proxyUsername' => null,
        'proxyPassword' => null,
        'proxyAuthenticationScheme' => Constants::AUTH_BASIC,
        'redirects' => 5,
        'timeout' => 5,
        'userAgent' => 'Horde\Http H6',
        'verifyPeer' => true
    ];

    public function __construct(iterable $param = [])
    {
        foreach ($param as $key => $value) {
            $this->setOption($key, $value);
        }
    }

    public function setOption(string $name, $value): void
    {
        $this->options[$name] = $value;
    }

    public function getOption(string $name)
    {
        return $this->options[$name] ?? null;
    }
}