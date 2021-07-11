<?php
/**
 * NetworkException class conforming PSR-18 Http Client interface
 */
declare(strict_types=1);
namespace Horde\Http;
use Psr\Http\Client\RequestExceptionInterface;
use Psr\Http\Message\RequestInterface;


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