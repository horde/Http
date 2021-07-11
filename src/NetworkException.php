<?php
/**
 * NetworkException class conforming PSR-18 Http Client interface
 */
declare(strict_types=1);
namespace Horde\Http;
use Psr\Http\Client\NetworkExceptionInterface;
use Psr\Http\Message\RequestInterface;


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