<?php
declare(strict_types=1);
namespace Horde\Http;
use \Psr\Http\Message\StreamInterface;
use InvalidArgumentException;
/**
 * Static utilities for PSR-7 Stream objects
 */
class StreamUtils
{
    /**
     * 
     */
    const MB16 = 16777216;

    public static function copyStreamToResource(StreamInterface $stream, $resource, int $buffer = self::MB16)
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