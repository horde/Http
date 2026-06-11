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

use DateTimeImmutable;

/**
 * HTTP cookie value object.
 *
 * Models a single cookie according to RFC 6265 / RFC 6265bis. The interface
 * is the public contract; {@see StrictCookie} is the framework's default
 * implementation. Alternative implementations (looser validation, signed
 * payloads, etc.) implement this interface directly or wrap a default
 * instance.
 *
 * Implementations MUST be immutable. Every with*() method returns a fresh
 * instance with the requested change applied.
 *
 * The formatter side is delegated to {@see CookieParser}; implementations
 * call into it from {@see toSetCookieHeader()} and {@see toCookieHeader()}.
 */
interface Cookie
{
    public function name(): string;

    public function value(): string;

    public function expires(): ?DateTimeImmutable;

    public function maxAge(): ?int;

    public function domain(): ?string;

    public function path(): string;

    public function secure(): bool;

    public function httpOnly(): bool;

    public function sameSite(): SameSite;

    public function withValue(string $value): self;

    public function withExpires(?DateTimeImmutable $expires): self;

    public function withMaxAge(?int $maxAge): self;

    public function withDomain(?string $domain): self;

    public function withPath(string $path): self;

    public function withSecure(bool $secure = true): self;

    public function withHttpOnly(bool $httpOnly = true): self;

    public function withSameSite(SameSite $sameSite): self;

    /**
     * Render as a Set-Cookie header value (server-side emission).
     *
     * Example: "name=value; Path=/; HttpOnly; SameSite=Lax"
     */
    public function toSetCookieHeader(): string;

    /**
     * Render as a single name=value pair for a Cookie request header
     * (client-side emission). No attributes; just the pair.
     */
    public function toCookieHeader(): string;

    /**
     * Return a "delete this cookie" cookie matching this one's scope.
     *
     * Path and Domain are preserved; Max-Age is set to 0; the value is
     * cleared. Browsers see the result as a deletion request iff the
     * Path and Domain match the original Set-Cookie that established
     * the cookie they hold.
     */
    public function deletion(): self;
}
