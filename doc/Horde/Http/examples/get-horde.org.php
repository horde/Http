<?php
/**
 * Basic example for fetching a page with Horde\Http\Client
 *
 * Copyright 2007-2017 Horde LLC (http://www.horde.org/)
 *
 * @author   Chuck Hagenbuch <chuck@horde.org>
 * @license  http://www.horde.org/licenses/bsd BSD
 * @category Horde
 * @package  Http
 */

require __DIR__ . '/../../../../vendor/autoload.php';
use Horde\Http\Client\Curl as CurlClient;
use Horde\Http\RequestFactory;
use Horde\Http\ResponseFactory;
use Horde\Http\StreamFactory;
use Horde\Http\Client\Options as ClientOptions;

// Old-Style client

// HTTP
$client = new Horde_Http_Client();
$response = $client->get('http://www.horde.org/');
var_dump($response);
echo $response->getBody();

// HTTPS
$client = new Horde_Http_Client();
$response = $client->get('https://www.horde.org/');
var_dump($response);
echo $response->getBody();

// PSR-18 client

$client = new CurlClient(
    new ResponseFactory,
    new StreamFactory,
    new ClientOptions
);
print("Modern PSR-18 Curl Client: Plain HTTP GET\n");
$requestFactory = new RequestFactory;
$request = $requestFactory->createRequest('GET', 'http://www.horde.org');
$response = $client->sendRequest($request);
print($response->getStatusCode() . "\n\n" . $response->getReasonPhrase() ."\n\n");

print("Modern PSR-18 Curl Client: HTTPS GET\n");
$requestFactory = new RequestFactory;
$request = $requestFactory->createRequest('GET', 'https://www.horde.org');
$response = $client->sendRequest($request);
print($response->getStatusCode() . "\n\n" . $response->getReasonPhrase() ."\n\n");

print("Modern PSR-18 Curl Client: HTTPS GitHub\n");
$requestFactory = new RequestFactory;
$request = $requestFactory->createRequest('GET', 'https://api.github.com/');
$response = $client->sendRequest($request);
print($response->getStatusCode() . "\n\n" . $response->getReasonPhrase() ."\n\n");
print($response->getBody()->getContents());