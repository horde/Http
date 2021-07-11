<?php
/**
 * NetworkException class conforming PSR-18 Http Client interface
 */
declare(strict_types=1);
namespace Horde\Http;
use Psr\Http\Client\ClientExceptionInterface;

/**
 * Every HTTP client related exception MUST implement this interface.
 */
class ClientException extends Exception implements ClientExceptionInterface
{
    
}