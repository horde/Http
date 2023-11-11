<?php
declare(strict_types=1);
namespace Horde\Http;
use Psr\Http\Message\StreamInterface;
use RuntimeException;
use Throwable;
use feof;
use fclose;
use ftell;
use fwrite;
use InvalidArgumentException;

/**
 * A PSR-7 compliant Stream implementation.
 *
 * This might be factored out to a Horde\Stream\ package but this is out of scope for now
 *
 * Typically, an instance will wrap a PHP stream; this interface provides
 * a wrapper around the most common operations, including serialization of
 * the entire stream to a string.
 */
class Stream implements StreamInterface
{
    /**
     * Cannot type for resource in php 7.x so keep this annotation
     *
     * @var resource $stream
     */
    protected $stream;
    protected bool $seekable;
    protected bool $readable;
    protected bool $writable;
    protected ?int $size = null;
    protected ?string $uri = null;

    /**
     * Stream constructor
     */
    public function __construct($stream)
    {
        // Cannot typehint for resource
        if (!is_resource($stream)) {
            throw new InvalidArgumentException('First argument to Stream constuctor must be a resource');
        }
        $this->stream = $stream;
        $meta = stream_get_meta_data($this->stream);
        $this->seekable = $meta['seekable'];
        $this->readable = in_array($meta['mode'], Constants::READABLE_STREAM_MODES);
        $this->writable = in_array($meta['mode'], Constants::WRITABLE_STREAM_MODES);
        $this->uri = $meta['uri'];
    }
    /**
     * Reads all data from the stream into a string, from the beginning to end.
     *
     * This method MUST attempt to seek to the beginning of the stream before
     * reading data and read the stream until the end is reached.
     *
     * Warning: This could attempt to load a large amount of data into memory.
     *
     * This method MUST NOT raise an exception in order to conform with PHP's
     * string casting operations.
     *
     * @see http://php.net/manual/en/language.oop5.magic.php#object.tostring
     * @return string
     */
    public function __toString()
    {
        try {
            if ($this->isSeekable()) {
                $this->seek(0);
            }
            return $this->getContents();
        } catch (Throwable $t) {
            // TODO: Cannot throw due to spec even though PHP now can. Log?
        }
        return '';
    }

    /**
     * Closes the stream and any underlying resources.
     *
     * @return void
     */
    public function close(): void
    {
        if (!empty($this->stream)) {
            fclose($this->stream);
            $this->detach();
        }
    }

    /**
     * Separates any underlying resources from the stream.
     *
     * After the stream has been detached, the stream is in an unusable state.
     *
     * @return resource|null Underlying PHP stream, if any
     */
    public function detach()
    {
        if (empty($this->stream)) {
            return null;
        }
        $ret = $this->stream;
        unset($this->stream);
        $this->size = null;
        $this->uri = null;
        $this->readable = false;
        $this->writable = false;
        $this->seekable = false;
    }

    /**
     * Get the size of the stream if known.
     *
     * @return int|null Returns the size in bytes if known, or null if unknown.
     */
    public function getSize(): ?int
    {
        return $this->size;
    }

    /**
     * Returns the current position of the file read/write pointer
     *
     * @return int Position of the file pointer
     * @throws \RuntimeException on error.
     */
    public function tell(): int
    {
        $res = ftell($this->stream);
        if ($res === false) {
            throw new RuntimeException('Could not determine current stream position');
        }
        return $res;
    }

    /**
     * Returns true if the stream is at the end of the stream.
     *
     * @return bool
     */
    public function eof(): bool
    {
        return $this->stream ? feof($this->stream) : true;
    }

    /**
     * Returns whether or not the stream is seekable.
     *
     * @return bool
     */
    public function isSeekable(): bool
    {
        return $this->seekable;
    }

    /**
     * Seek to a position in the stream.
     *
     * @link http://www.php.net/manual/en/function.fseek.php
     * @param int $offset Stream offset
     * @param int $whence Specifies how the cursor position will be calculated
     *     based on the seek offset. Valid values are identical to the built-in
     *     PHP $whence values for `fseek()`.  SEEK_SET: Set position equal to
     *     offset bytes SEEK_CUR: Set position to current location plus offset
     *     SEEK_END: Set position to end-of-stream plus offset.
     * @throws \RuntimeException on failure.
     */
    public function seek(int $offset, $whence = SEEK_SET): void
    {
        if (!$this->seekable) {
            throw new RuntimeException('Could not seek this stream');
        }
        $res = fseek($this->stream, $offset, $whence);
        if ($res == -1) {
            throw new RuntimeException('Could not seek desired position');
        }
    }

    /**
     * Seek to the beginning of the stream.
     *
     * If the stream is not seekable, this method will raise an exception;
     * otherwise, it will perform a seek(0).
     *
     * @see seek()
     * @link http://www.php.net/manual/en/function.fseek.php
     * @throws \RuntimeException on failure.
     */
    public function rewind(): void
    {
        $this->seek(0);
    }

    /**
     * Returns whether or not the stream is writable.
     *
     * @return bool
     */
    public function isWritable(): bool
    {
        return $this->writable;
    }

    /**
     * Write data to the stream.
     *
     * @param string $string The string that is to be written.
     * @return int Returns the number of bytes written to the stream.
     * @throws \RuntimeException on failure.
     */
    public function write($string): int
    {
        $res = fwrite($this->stream, $string);
        if ($res === false) {
            throw new RuntimeException('Could not write to stream');
        }
        return $res;
    }

    /**
     * Returns whether or not the stream is readable.
     *
     * @return bool
     */
    public function isReadable(): bool
    {
        return $this->readable;
    }

    /**
     * Read data from the stream.
     *
     * @param int $length Read up to $length bytes from the object and return
     *     them. Fewer than $length bytes may be returned if underlying stream
     *     call returns fewer bytes.
     * @return string Returns the data read from the stream, or an empty string
     *     if no bytes are available.
     * @throws \RuntimeException if an error occurs.
     */
    public function read(int $length): string
    {
        $data = empty($length) ? '' : fread($this->stream, $length);
        if ($data === false) {
            throw new RuntimeException('Could not read stream');
        }
        return $data;
    }

    /**
     * Returns the remaining contents in a string
     *
     * @return string
     * @throws \RuntimeException if unable to read or an error occurs while
     *     reading.
     */
    public function getContents(): string
    {
        $contents = stream_get_contents($this->stream);
        if ($contents === false) {
            throw new RuntimeException('Could not read stream');
        }
        return $contents;
    }

    /**
     * Get stream metadata as an associative array or retrieve a specific key.
     *
     * The keys returned are identical to the keys returned from PHP's
     * stream_get_meta_data() function.
     *
     * @link http://php.net/manual/en/function.stream-get-meta-data.php
     * @param string $key Specific metadata to retrieve.
     * @return array|mixed|null Returns an associative array if no key is
     *     provided. Returns a specific key value if a key is provided and the
     *     value is found, or null if the key is not found.
     */
    public function getMetadata($key = null)
    {
        if (!isset($this->stream)) {
            return $key ? null : [];
        }
        $meta = stream_get_meta_data($this->stream);
        if ($key) {
            return $meta[$key] ?? null;
        }
        return $meta;
    }
}
