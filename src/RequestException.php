<?php

/**
 * NetworkException class conforming PSR-18 Http Client interface
 */
declare(strict_types=1);

namespace Horde\Http;

use Psr\Http\Client\RequestExceptionInterface;
use Psr\Http\Message\RequestInterface;
use Throwable;

/**
 * Exception for when a request failed.
 *
 * Examples:
 *      - Request is invalid (e.g. method is missing)
 *      - Runtime request errors (e.g. the body stream is not seekable)
 */
class RequestException extends Exception implements RequestExceptionInterface
{
    private RequestInterface $request;

    public function __construct(
        RequestInterface $request,
        string $message = '',
        int $code = 0,
        ?Throwable $previous = null,
    ) {
        // Horde_Exception_Wrapped's constructor only takes ($message, $code)
        // — it derives the chained previous from a Throwable passed as
        // $message. Preserve the caller's intent by passing $previous
        // through that mechanism when the caller supplied one and $message
        // was left empty.
        if ($previous !== null && $message === '') {
            parent::__construct($previous, $code);
        } else {
            parent::__construct($message, $code);
        }
        $this->request = $request;
    }

    /**
     * Returns the request.
     *
     * The request object MAY be a different object from the one passed to ClientInterface::sendRequest()
     *
     * @return RequestInterface
     */
    public function getRequest(): RequestInterface
    {
        return $this->request;
    }
}
