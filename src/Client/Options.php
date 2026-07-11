<?php

/**
 * Copyright 2020-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (BSD). If you
 * did not receive this file, see http://www.horde.org/licenses/bsd.
 *
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
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Horde\Http\Constants;
use TypeError;

/**
 * An Options container for HTTP clients
 */
class Options
{
    /**
     * Backing store for named options. Values are heterogenous — timeout
     * and redirects are ints, userAgent is a string, verifyPeer is a bool,
     * credentials may be strings or null.
     *
     * @var array<string, mixed>
     */
    protected array $options = [
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
        'verifyPeer' => true,
    ];

    /**
     * @param iterable<string, mixed> $param Named option overrides.
     */
    public function __construct(iterable $param = [])
    {
        foreach ($param as $key => $value) {
            $this->setOption($key, $value);
        }
    }

    /**
     * @param mixed $value New value for the option.
     */
    public function setOption(string $name, mixed $value): void
    {
        $this->options[$name] = $value;
    }

    public function getOption(string $name): mixed
    {
        return $this->options[$name] ?? null;
    }

    /**
     * Fetch $name as a string, or null if it is unset or null.
     *
     * @throws TypeError When the stored value is neither string nor null.
     */
    public function getString(string $name): ?string
    {
        $value = $this->options[$name] ?? null;
        if ($value === null) {
            return null;
        }
        if (!is_string($value)) {
            throw new TypeError(sprintf('Option %s is %s, expected string', $name, get_debug_type($value)));
        }
        return $value;
    }

    /**
     * Fetch $name as an int, or null if it is unset or null.
     *
     * @throws TypeError When the stored value is neither int nor null.
     */
    public function getInt(string $name): ?int
    {
        $value = $this->options[$name] ?? null;
        if ($value === null) {
            return null;
        }
        if (!is_int($value)) {
            throw new TypeError(sprintf('Option %s is %s, expected int', $name, get_debug_type($value)));
        }
        return $value;
    }

    /**
     * Fetch $name as a bool. Missing/null defaults to $default.
     */
    public function getBool(string $name, bool $default = false): bool
    {
        $value = $this->options[$name] ?? null;
        if ($value === null) {
            return $default;
        }
        return (bool) $value;
    }
}
