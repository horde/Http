<?php

declare(strict_types=1);

/**
 * Copyright 2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (BSD). If you
 * did not receive this file, see http://www.horde.org/licenses/bsd.
 *
 * @category Horde
 * @license  http://www.horde.org/licenses/bsd BSD-2-Clause
 * @package  Http
 */

namespace Horde\Http;

/**
 * Typed view over a request's parsed cookies.
 *
 * Shaped to wrap the result of PSR-7's
 * {@see \Psr\Http\Message\ServerRequestInterface::getCookieParams()}
 * (a flat associative array of name => value), and to parse a raw
 * Cookie request header string into the same shape via
 * {@see fromCookieHeader()}.
 *
 * Read-only. The instance is the request side of the cookie story; if
 * you want to emit cookies on a response, build {@see Cookie} instances
 * and use the server-side glue (`Horde\Http\Server\Cookies::with()`).
 *
 * Repeated names: last-wins, matching the convention browsers use when
 * emitting cookies in path-most-specific order.
 */
final class CookieList
{
    /** @param array<string, string> $cookies */
    public function __construct(private readonly array $cookies) {}

    /**
     * Build from PSR-7's getCookieParams() output. Pass-through
     * constructor; provided for symmetry with fromCookieHeader().
     *
     * @param array<string, string> $params
     */
    public static function fromCookieParams(array $params): self
    {
        return new self($params);
    }

    /**
     * Parse a Cookie request header value into a list.
     *
     * Empty/whitespace-only headers produce an empty list.
     */
    public static function fromCookieHeader(string $header): self
    {
        return new self(CookieParser::parseCookieHeader($header));
    }

    public function get(string $name): ?string
    {
        return $this->cookies[$name] ?? null;
    }

    public function has(string $name): bool
    {
        return array_key_exists($name, $this->cookies);
    }

    /**
     * @return list<string>
     */
    public function names(): array
    {
        return array_keys($this->cookies);
    }

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return $this->cookies;
    }

    public function count(): int
    {
        return count($this->cookies);
    }
}
