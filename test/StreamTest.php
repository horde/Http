<?php

namespace Horde\Http\Test;

use Phpunit\Framework\TestCase;
use Horde\Http\Stream;
use Psr\Http\Message\StreamInterface;
use InvalidArgumentException;

class StreamTest extends TestCase
{
    public function setUp(): void
    {
    }

    public function testIsSeekable()
    {
        $stream = new Stream(fopen('php://temp', 'r'));
        $isReadable = $stream->isSeekable();
        $this->assertSame(true, $isReadable);;
    }

    public function testIsNotSeekable()
    {
        $stream = new Stream(fopen('php://temp', 'r'));
        $stream->close();
        $isReadable = $stream->isSeekable();
        $this->assertSame(false, $isReadable);;
    }

    public function testExceptionWhenNoResource()
    {
        $this->expectException(InvalidArgumentException::class);
        $stream = new Stream('test');
    }

    public function testIsWriteable()
    {
        $stream = new Stream(fopen('php://temp', 'r+'));
        $isWritable = $stream->isWritable();
        $this->assertSame(true, $isWritable);
    }

    public function testWriteToString()
    {
        $stream = new Stream(fopen('php://temp', 'r+'));
        $stream->write('123456789TestXYZ');
        $toString = $stream->__toString();
        $this->assertSame('123456789TestXYZ', $toString);
    }

    public function testWriteToStringWithUnseekable()
    {
        $stream = new Stream(fopen('php://temp', 'a+'));
        $stream->write('123456789TestXYZ');
        $toString = $stream->__toString();
        $this->assertSame('123456789TestXYZ', $toString);
    }

    public function testGetSize()
    {
        $stream = new Stream(fopen('php://temp', 'r+'));
        $stream->write('123456789TestXYZ');
        $size = $stream->getSize();
        $this->assertSame(null, $size);
    }

    public function testTellImmediately()
    {
        $stream = new Stream(fopen('php://temp', 'r+'));
        $tell = $stream->tell();
        $this->assertSame(0, $tell);;
    }

    public function testTell()
    {
        $stream = new Stream(fopen('php://temp', 'r+'));
        $stream->write('123456789TestXYZ');
        $tell = $stream->tell();
        $this->assertSame(16, $tell);;
    }

    public function testWriteReadAndEof()
    {
        $stream = new Stream(fopen('php://temp', 'r+'));
        $stream->write('123456789TestXYZ');
        $stream->read(16);
        $eof = $stream->eof();
        $this->assertSame(true, $eof);
    }

    public function testSeek()
    {
        $stream = new Stream(fopen('php://temp', 'r+'));
        $stream->write('123456789TestXYZ');
        $stream->seek(9);
        $seeked = $stream->read(4);
        $this->assertSame("Test", $seeked);
    }

    public function testRewind()
    {
        $stream = new Stream(fopen('php://temp', 'r+'));
        $stream->write('123456789TestXYZ');
        $stream->read(4);
        $stream->rewind();
        $rewinded = $stream->read(9);
        $this->assertSame('123456789', $rewinded);
    }

    public function testGetContents()
    {
        $stream = new Stream(fopen('php://temp', 'r+'));
        $stream->write('123456789TestXYZ');
        $stream->rewind();
        $stream->read(9);
        $content = $stream->getContents();
        $this->assertSame('TestXYZ', $content);
    }

    public function testGetMetadata()
    {
        $stream = new Stream(fopen('php://temp', 'r+'));
        $meta = $stream->getMetadata('wrapper_type');
        $this->assertSame('PHP', $meta);
    }

    public function testGetMetadataInvalidKey()
    {
        $stream = new Stream(fopen('php://temp', 'r+'));
        $meta = $stream->getMetadata('invalid_key');
        $this->assertSame(null, $meta);
    }

    public function testGetMetadataFullArray()
    {
        $stream = new Stream(fopen('php://temp', 'r+'));
        $meta = $stream->getMetadata();
        $this->assertSame([
            'wrapper_type' => 'PHP',
            'stream_type' => 'TEMP',
            'mode' => 'w+b',
            'unread_bytes' => 0,
            'seekable' => true,
            'uri' => 'php://temp',
        ], $meta);
    }

    public function testDetach()
    {
        $stream = new Stream(fopen('php://temp', 'r+'));
        $stream->detach();
        $isReadable = $stream->isReadable();
        $this->assertSame(false, $isReadable);
    }

    public function testDetachWithMetaData()
    {
        $stream = new Stream(fopen('php://temp', 'r+'));
        $stream->detach();
        $meta = $stream->getMetadata();
        $this->assertSame([], $meta);
    }
}
