<?php

declare(strict_types=1);

namespace Horde\Http;

use Psr\Http\Message\StreamInterface;
use InvalidArgumentException;

/**
 * Static utilities for PSR-7 Stream objects
 */
class StreamUtils
{
    /**
     *
     */
    public const MB16 = 16777216;

    /**
     * Copy the contents of a PSR-7 stream into an already-open resource.
     *
     * @param StreamInterface $stream Source stream.
     * @param resource $resource Destination resource (checked at runtime).
     * @param int $buffer Read chunk size in bytes.
     * @return resource The destination resource for chaining.
     * @throws InvalidArgumentException When $resource is not a resource.
     */
    public static function copyStreamToResource(StreamInterface $stream, mixed $resource, int $buffer = self::MB16): mixed
    {
        if (!is_resource($resource)) {
            throw new InvalidArgumentException('Second Parameter $resource must be resource');
        }
        while (!$stream->eof()) {
            fwrite($resource, $stream->read($buffer));
        }
        return $resource;
    }
}
