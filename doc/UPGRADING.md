# Upgrading Http

## Version 3.0.0

### Breaking Changes

#### PSR Standards Replace Legacy API

Horde_Http 3.0 adopts PSR-7 (HTTP messages), PSR-17 (HTTP factories), and PSR-18 (HTTP client) as the primary API. The legacy `Horde_Http_Client` is maintained for backward compatibility but may be deprecated in future versions.

**Migration:** Use PSR-compliant interfaces instead of legacy classes.

**Before (Horde 5 / Http 2.x):**
```php
use Horde_Http_Client;

$client = new Horde_Http_Client();
$response = $client->get('https://api.example.com/data');
$body = $response->getBody();
```

**After (Horde 6 / Http 3.x):**
```php
use Horde\Http\Client;
use Horde\Http\RequestFactory;

$client = new Client();
$requestFactory = new RequestFactory();

$request = $requestFactory->createRequest('GET', 'https://api.example.com/data');
$response = $client->sendRequest($request);
$body = (string) $response->getBody();
```

#### Namespace Changes

Classes moved from PSR-0 (`Horde_Http_*`) to PSR-4 (`Horde\Http\*`):

| Horde 5 (PSR-0) | Horde 6 (PSR-4) |
|----------------|----------------|
| `Horde_Http_Client` | `Horde\Http\Client` (PSR-18) |
| `Horde_Http_Request` | `Psr\Http\Message\RequestInterface` (PSR-7) |
| `Horde_Http_Response` | `Psr\Http\Message\ResponseInterface` (PSR-7) |
| N/A | `Horde\Http\RequestFactory` (PSR-17) |
| N/A | `Horde\Http\ResponseFactory` (PSR-17) |
| N/A | `Horde\Http\StreamFactory` (PSR-17) |
| N/A | `Horde\Http\Uri` (PSR-7) |

#### Return Type Changes

**Response Body:**
```php
// Before (Http 2.x) - returns string
$body = $response->getBody();

// After (Http 3.x) - returns StreamInterface
$body = (string) $response->getBody();  // Cast to string
// Or
$body = $response->getBody()->getContents();  // Read stream
```

**Request URI:**
```php
// Before (Http 2.x) - accepts string or Horde_Url
$client->get('https://example.com');

// After (Http 3.x) - PSR-7 UriInterface
$uri = new Horde\Http\Uri('https://example.com');
$request = $requestFactory->createRequest('GET', $uri);
$response = $client->sendRequest($request);
```

### PHP Version

Http 3.0 requires PHP 7.4 or higher.

### New Features

#### PSR-7 HTTP Messages

Immutable request/response objects following PSR-7 standard:

```php
use Horde\Http\Request;
use Horde\Http\Response;
use Horde\Http\Uri;

// Create request
$request = new Request('GET', new Uri('https://api.example.com/users'));
$request = $request->withHeader('Accept', 'application/json');
$request = $request->withHeader('Authorization', 'Bearer ' . $token);

// Create response
$response = new Response(200, ['Content-Type' => 'application/json'], '{"status":"ok"}');
```

**Immutability:** All `with*()` methods return new instances:
```php
$request1 = new Request('GET', 'https://example.com');
$request2 = $request1->withMethod('POST');

// $request1 still has GET method
// $request2 has POST method
```

#### PSR-17 HTTP Factories

Factory interfaces for creating HTTP objects:

```php
use Horde\Http\RequestFactory;
use Horde\Http\ResponseFactory;
use Horde\Http\StreamFactory;
use Horde\Http\UriFactory;

$requestFactory = new RequestFactory();
$responseFactory = new ResponseFactory();
$streamFactory = new StreamFactory();
$uriFactory = new UriFactory();

// Create request with factory
$request = $requestFactory->createRequest('GET', 'https://example.com');

// Create response with factory
$response = $responseFactory->createResponse(200, 'OK');

// Create stream from string
$stream = $streamFactory->createStream('{"data":"value"}');
```

#### PSR-18 HTTP Client

Standard client interface for sending requests:

```php
use Horde\Http\Client;
use Psr\Http\Client\ClientInterface;

// Client implements PSR-18 ClientInterface
$client = new Client();

// Send any PSR-7 RequestInterface
$response = $client->sendRequest($request);
```

**Benefits:**
- Interoperable with any PSR-7/PSR-18 compatible library
- Mockable for testing
- Framework-agnostic

#### Request Body Handling

**IMPORTANT:** PHP's `php://input` stream is non-seekable and can only be read once.

**For JSON/Form Data (Recommended):**
```php
// Use getParsedBody() instead of reading body directly
$data = $request->getParsedBody();  // array|object|null

// With JsonBodyParser middleware (recommended)
use Horde\Http\Server\Middleware\JsonBodyParser;

$mapper->connect('api_endpoint', '/api/v1/endpoint', [
    'controller' => MyApiController::class,
    'stack' => [JsonBodyParser::class],
]);
```

**For Raw Body Access:**
```php
// Read once and cache
$raw = (string) $request->getBody();
$request = $request->withAttribute('raw_body', $raw);
```

See [doc/REQUEST_BODY_HANDLING.md](REQUEST_BODY_HANDLING.md) for detailed patterns.

### Migration Strategies

#### Strategy 1: Keep Using Legacy Wrapper

Minimal changes required. Legacy `Horde_Http_Client` delegates to PSR implementation:

```php
// Still works in Horde 6
use Horde_Http_Client;

$client = new Horde_Http_Client([
    'request.timeout' => 30,
]);

$response = $client->get('https://api.example.com/data');
```

**Note:** Legacy API may be deprecated in future major version.

#### Strategy 2: Migrate to PSR Standards (Recommended)

Update to modern PSR-compliant code:

```php
use Horde\Http\Client;
use Horde\Http\RequestFactory;

$client = new Client(['timeout' => 30]);
$factory = new RequestFactory();

$request = $factory->createRequest('GET', 'https://api.example.com/data');
$response = $client->sendRequest($request);

$data = json_decode((string) $response->getBody(), true);
```

#### Strategy 3: Use HordeClientWrapper (Bridge)

Wrap PSR client with legacy-compatible interface:

```php
use Horde\Http\HordeClientWrapper;
use Horde\Http\Client;

$psrClient = new Client();
$client = new HordeClientWrapper($psrClient);

// Use legacy-style API with PSR implementation
$response = $client->get('https://example.com');
```

### Dependency Injection

Http 3.0 is designed for dependency injection:

```php
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;

class ApiService
{
    public function __construct(
        private ClientInterface $httpClient,
        private RequestFactoryInterface $requestFactory
    ) {}

    public function fetchData(string $url): array
    {
        $request = $this->requestFactory->createRequest('GET', $url);
        $response = $this->httpClient->sendRequest($request);

        return json_decode((string) $response->getBody(), true);
    }
}

// Inject Horde implementations
$service = new ApiService(
    new Horde\Http\Client(),
    new Horde\Http\RequestFactory()
);
```

### Exception Handling

```php
use Psr\Http\Client\ClientExceptionInterface;
use Horde\Http\NetworkException;
use Horde\Http\ClientException;

try {
    $response = $client->sendRequest($request);
} catch (NetworkException $e) {
    // Network errors (timeout, connection failure)
} catch (ClientException $e) {
    // Client errors (invalid request)
} catch (ClientExceptionInterface $e) {
    // Any PSR-18 client exception
}
```

### Testing

Mock PSR-7/PSR-18 interfaces for tests:

```php
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

// Mock client
$mockClient = $this->createMock(ClientInterface::class);
$mockResponse = $this->createMock(ResponseInterface::class);

$mockClient->expects($this->once())
    ->method('sendRequest')
    ->with($this->isInstanceOf(RequestInterface::class))
    ->willReturn($mockResponse);
```

### Removed Features

#### Horde_Url Integration (Partial)

PSR-7 uses `UriInterface`, not `Horde_Url`. Use `Horde\Http\Uri` instead:

```php
// Old
use Horde_Url;
$url = new Horde_Url('https://example.com');

// New
use Horde\Http\Uri;
$uri = new Uri('https://example.com');
```

**Note:** Some bridge code exists for compatibility. See `HordeClientWrapper` for legacy integration.

### Benefits of Upgrading

1. **Standards Compliance** - Full PSR-7/PSR-17/PSR-18 support
2. **Framework Agnostic** - Works with any PSR-compliant code
3. **Better Testing** - Easy mocking with PSR interfaces
4. **Immutability** - Thread-safe request/response objects
5. **Dependency Injection** - Clean architecture with interface injection
6. **Modern PHP** - Strict types, typed properties, PHP 7.4+ features

### Comparison Table

| Feature | Http 2.x (PSR-0) | Http 3.x (PSR-4) |
|---------|------------------|------------------|
| PHP Version | 5.3+ / 7.0+ | 7.4+ / 8.0+ |
| Namespace | `Horde_Http_*` | `Horde\Http\*` |
| Client API | Custom | PSR-18 ClientInterface |
| Request/Response | Custom | PSR-7 MessageInterface |
| Factories | N/A | PSR-17 Factories |
| Immutability | No | Yes (PSR-7) |
| Type Safety | Minimal | Strict types |
| Interoperability | Horde-specific | PSR-compliant |

### Timeline

- **3.0.0-alpha1** (2021) - Initial PSR implementation
- **3.0.0-beta1** (2025) - Advertise PSR implementations
- **3.0.0-beta4** (2026) - Current, production-ready
- **3.0.0** (Future) - Stable release
- **4.0.0** (Future) - Legacy wrappers may be deprecated

### Support

For migration assistance, see:
- [REQUEST_BODY_HANDLING.md](REQUEST_BODY_HANDLING.md) - Request body patterns
- [PSR-7 Specification](https://www.php-fig.org/psr/psr-7/)
- [PSR-17 Specification](https://www.php-fig.org/psr/psr-17/)
- [PSR-18 Specification](https://www.php-fig.org/psr/psr-18/)
- [Horde Mailing List](https://lists.horde.org/)
