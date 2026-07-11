<?php

declare(strict_types=1);

namespace Horde\Http;

use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

/**
 * A PSR-7 HTTP request message for Horde
 */
class Response implements ResponseInterface
{
    use MessageImplementation;

    /**
     * The HTTP Reason Phrase. May be a standard from lookup table, freeform,
     * or null when the caller did not supply one and getReasonPhrase() has
     * not yet had to fall back to the default table.
     */
    private ?string $httpReasonPhrase;

    /**
     * The HTTP Status Code.
     */
    private int $httpStatusCode;


    /**
     * @param int $status HTTP status code.
     * @param iterable<string, string|list<string>> $headers Response headers.
     * @param string|resource|StreamInterface|null $body Response body.
     * @param string $version HTTP protocol version.
     * @param string|null $reason HTTP reason phrase override.
     */
    public function __construct(int $status = 200, iterable $headers = [], $body = null, string $version = '1.1', ?string $reason = null)
    {
        $this->httpStatusCode = $status;
        $this->httpReasonPhrase = $reason;

        foreach ($headers as $header => $value) {
            $this->storeHeader($header, $value);
        }
        if ($body instanceof StreamInterface) {
            $this->stream = $body;
        } elseif (is_string($body) && $body) {
            $factory = new StreamFactory();
            $this->stream = $factory->createStream($body);
        }
        $this->protocolVersion = $version;
    }

    /**
     * Gets the response status code.
     *
     * The status code is a 3-digit integer result code of the server's attempt
     * to understand and satisfy the request.
     *
     * @return int Status code.
     */
    public function getStatusCode(): int
    {
        return $this->httpStatusCode;
    }

    /**
     * Return an instance with the specified status code and, optionally, reason phrase.
     *
     * If no reason phrase is specified, implementations MAY choose to default
     * to the RFC 7231 or IANA recommended reason phrase for the response's
     * status code.
     *
     * This method MUST be implemented in such a way as to retain the
     * immutability of the message, and MUST return an instance that has the
     * updated status and reason phrase.
     *
     * @link http://tools.ietf.org/html/rfc7231#section-6
     * @link http://www.iana.org/assignments/http-status-codes/http-status-codes.xhtml
     * @param int $code The 3-digit integer result code to set.
     * @param string $reasonPhrase The reason phrase to use with the
     *     provided status code; if none is provided, implementations MAY
     *     use the defaults as suggested in the HTTP specification.
     * @return static
     * @throws InvalidArgumentException For invalid status code arguments.
     */
    public function withStatus(int $code, string $reasonPhrase = ''): ResponseInterface
    {
        if (!array_key_exists($code, Constants::REASON_PHRASES)) {
            throw new InvalidArgumentException('Invalid HTTP status code: ' . $code);
        }
        $ret = clone($this);
        $ret->httpStatusCode = $code;
        if ($reasonPhrase) {
            $ret->httpReasonPhrase = $reasonPhrase;
        } else {
            $ret->httpReasonPhrase = Constants::REASON_PHRASES[$code];
        }
        return $ret;
    }

    /**
     * Gets the response reason phrase associated with the status code.
     *
     * Because a reason phrase is not a required element in a response
     * status line, the reason phrase value MAY be null. Implementations MAY
     * choose to return the default RFC 7231 recommended reason phrase (or those
     * listed in the IANA HTTP Status Code Registry) for the response's
     * status code.
     *
     * @link http://tools.ietf.org/html/rfc7231#section-6
     * @link http://www.iana.org/assignments/http-status-codes/http-status-codes.xhtml
     * @return string Reason phrase; must return an empty string if none present.
     */
    public function getReasonPhrase(): string
    {
        if ($this->httpReasonPhrase) {
            return $this->httpReasonPhrase;
        }
        return Constants::REASON_PHRASES[$this->httpStatusCode];
    }
}
