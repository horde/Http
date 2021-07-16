<?php
declare(strict_types=1);
namespace Horde\Http;
use \Horde_String;
use Psr\Http\Message\UriInterface;
use Psr\Http\Message\UriFactoryInterface;

/**
 * Uri Factory
 * 
 * Implemented for abstraction and completeness
 * The Uri Constructor does the real work.
 * 
 */
class UriFactory implements UriFactoryInterface
{
    public function createUri(string $uri = ''): UriInterface
    {
        return new Uri($uri);
    }
}