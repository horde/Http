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
 * SameSite cookie attribute.
 *
 * Maps directly onto the SameSite token defined by RFC 6265bis. The enum
 * cases use the canonical capitalized strings that go on the wire in a
 * Set-Cookie header.
 *
 * - {@see SameSite::Strict}: cookie is only sent on same-site requests.
 *   Highest protection against CSRF; breaks "follow link from email"
 *   style first-visit flows.
 * - {@see SameSite::Lax}: cookie is sent on same-site requests and on
 *   top-level GET navigations from other sites. Modern browser default
 *   when the attribute is omitted; the right choice for most session
 *   cookies.
 * - {@see SameSite::None}: cookie is sent on all cross-site requests.
 *   Required for genuine third-party cookie use cases (embedded SaaS).
 *   Browsers reject SameSite=None cookies that are not also marked
 *   Secure; constructors of {@see Cookie} implementations must enforce
 *   this pairing.
 */
enum SameSite: string
{
    case Strict = 'Strict';
    case Lax = 'Lax';
    case None = 'None';
}
