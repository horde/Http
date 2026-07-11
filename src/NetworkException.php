<?php

/**
 * NetworkException class conforming PSR-18 Http Client interface
 */
declare(strict_types=1);

namespace Horde\Http;

use Psr\Http\Client\NetworkExceptionInterface;
use Psr\Http\Message\RequestInterface;
use Throwable;

/**
 * Thrown when the request cannot be completed because of network issues.
 *
 * There is no response object as this exception is thrown when no response has been received.
 *
 * Example: the target host name can not be resolved or the connection failed.
 */
class NetworkException extends Exception implements NetworkExceptionInterface
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
