<?php
declare(strict_types=1);
namespace Horde\Http;

use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UploadedFileInterface;
use RuntimeException;
use InvalidArgumentException;
use Psr\Http\Message\StreamFactoryInterface;

class UploadedFile implements UploadedFileInterface
{


    protected ?string $originalName;
    protected ?string $mimeType;
    protected ?int $error;
    protected ?int $size;
    protected ?string $clientFileName;
    protected ?string $clientMediaType;
    protected ?StreamInterface $stream;
    private bool $hasCalledMoveTo = false;
    private StreamFactoryInterface $streamFactory;

    /**
     * $copyChunkSize
     * 
     * When copying a stream by reading and writing,
     * use chunks of 256k to prevent allocating large amounts of memory
     * 
     * @var int
     */
    private int $copyChunkSize = 262144;


    /**
     * 
     */
    public function __construct(
        StreamInterface $stream,
        StreamFactoryInterface $streamFactory,
        string $clientFileName,
        string $clientMediaType,
        ?int $error,
        ?int $size = null
    )
    {
        $this->stream = $stream;
        $this->streamFactory = $streamFactory;
        $this->size = $size;
        $this->error = $error;
        $this->clientFileName = $clientFileName;
        $this->clientMediaType = $clientMediaType;
    }
    /**
     * Retrieve a stream representing the uploaded file.
     *
     * This method MUST return a StreamInterface instance, representing the
     * uploaded file. The purpose of this method is to allow utilizing native PHP
     * stream functionality to manipulate the file upload, such as
     * stream_copy_to_stream() (though the result will need to be decorated in a
     * native PHP stream wrapper to work with such functions).
     *
     * If the moveTo() method has been called previously, this method MUST raise
     * an exception.
     *
     * @return StreamInterface Stream representation of the uploaded file.
     * @throws \RuntimeException in cases when no stream is available or can be
     *     created.
     */
    public function getStream()
    {
        // TODO: Any sanity checks on the stream
        if ($this->hasCalledMoveTo) {
            throw new RuntimeException('Calling getStream after moveTo not allowed by PSR-7 spec');
        }
        return $this->stream;
    }

    /**
     * Move the uploaded file to a new location.
     *
     * Use this method as an alternative to move_uploaded_file(). This method is
     * guaranteed to work in both SAPI and non-SAPI environments.
     * Implementations must determine which environment they are in, and use the
     * appropriate method (move_uploaded_file(), rename(), or a stream
     * operation) to perform the operation.
     *
     * $targetPath may be an absolute path, or a relative path. If it is a
     * relative path, resolution should be the same as used by PHP's rename()
     * function.
     *
     * The original file or stream MUST be removed on completion.
     *
     * If this method is called more than once, any subsequent calls MUST raise
     * an exception.
     *
     * When used in an SAPI environment where $_FILES is populated, when writing
     * files via moveTo(), is_uploaded_file() and move_uploaded_file() SHOULD be
     * used to ensure permissions and upload status are verified correctly.
     *
     * If you wish to move to a stream, use getStream(), as SAPI operations
     * cannot guarantee writing to stream destinations.
     *
     * @see http://php.net/is_uploaded_file
     * @see http://php.net/move_uploaded_file
     * @param string $targetPath Path to which to move the uploaded file.
     * @throws \InvalidArgumentException if the $targetPath specified is invalid.
     * @throws \RuntimeException on any error during the move operation, or on
     *     the second or subsequent call to the method.
     */
    public function moveTo($targetPath)
    {
        if ($this->hasCalledMoveTo) {
            throw new RuntimeException("Cannot call moveTo twice on same UploadedFile");
        }
        if (empty($targetPath) || !is_string($targetPath)) {
            throw new InvalidArgumentException("targetPath must be a non-empty string");
        }
        // Open a new stream from file handle
        $target = $this->streamFactory->createStreamFromFile($targetPath, 'r+');

        // Read/write chunks until eof
        while (!$this->stream->eof()) {
            $target->write($this->stream->read($this->copyChunkSize));
        }
        
        $this->hasCalledMoveTo = true;
    }
    
    /**
     * Retrieve the file size.
     *
     * Implementations SHOULD return the value stored in the "size" key of
     * the file in the $_FILES array if available, as PHP calculates this based
     * on the actual size transmitted.
     *
     * @return int|null The file size in bytes or null if unknown.
     */
    public function getSize()
    {
        return $this->size;
    }
    
    /**
     * Retrieve the error associated with the uploaded file.
     *
     * The return value MUST be one of PHP's UPLOAD_ERR_XXX constants.
     *
     * If the file was uploaded successfully, this method MUST return
     * UPLOAD_ERR_OK.
     *
     * Implementations SHOULD return the value stored in the "error" key of
     * the file in the $_FILES array.
     *
     * @see http://php.net/manual/en/features.file-upload.errors.php
     * @return int One of PHP's UPLOAD_ERR_XXX constants.
     */
    public function getError()
    {
        return $this->error;
    }
    
    /**
     * Retrieve the filename sent by the client.
     *
     * Do not trust the value returned by this method. A client could send
     * a malicious filename with the intention to corrupt or hack your
     * application.
     *
     * Implementations SHOULD return the value stored in the "name" key of
     * the file in the $_FILES array.
     *
     * @return string|null The filename sent by the client or null if none
     *     was provided.
     */
    public function getClientFilename()
    {
        return $this->clientFilename;        
    }
    
    /**
     * Retrieve the media type sent by the client.
     *
     * Do not trust the value returned by this method. A client could send
     * a malicious media type with the intention to corrupt or hack your
     * application.
     *
     * Implementations SHOULD return the value stored in the "type" key of
     * the file in the $_FILES array.
     *
     * @return string|null The media type sent by the client or null if none
     *     was provided.
     */
    public function getClientMediaType()
    {
        return $this->clientMediaType;
    }
}
