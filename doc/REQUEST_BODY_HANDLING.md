# Request Body Handling and the php://input Issue

## The Problem

PHP's `php://input` stream is **non-seekable**, meaning it can only be read once. This is a fundamental limitation of PHP, not specific to Horde or PSR-7.

When Horde\Http\Server\RequestBuilder creates a ServerRequest, it wraps `php://input` in a Stream object. Once you call `$request->getBody()->__toString()` or cast the body to a string, the stream position reaches EOF and cannot be rewound.

This affects:
- Controllers that need to read the body multiple times
- Middleware chains where multiple middlewares inspect the body
- Debugging scenarios where you want to log the raw body

## The PSR-7 Solution: getParsedBody()

PSR-7 ServerRequestInterface provides `getParsedBody()` specifically to solve this issue:

```php
// ❌ BAD: Reading body directly (non-seekable)
$body = (string) $request->getBody();
$data = json_decode($body, true);

// ✅ GOOD: Use getParsedBody() (cached by framework)
$data = $request->getParsedBody(); // array|object|null
```

## How to Use in Horde

### Option 1: Use JsonBodyParser Middleware (Recommended)

Add the middleware to routes that accept JSON:

```php
// In config/routes.local.php
$mapper->connect('api_endpoint', '/api/v1/endpoint', [
    'controller' => MyApiController::class,
    'stack' => [\Horde\Http\Server\Middleware\JsonBodyParser::class],
]);
```

In your controller:

```php
use Psr\Http\Message\ServerRequestInterface;

class MyApiController implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        // Body already parsed by middleware
        $body = $request->getParsedBody(); // array|null

        $username = $body['username'] ?? null;
        $password = $body['password'] ?? null;

        // ...
    }
}
```

### Option 2: Parse Manually (If Middleware Not Available)

```php
private function parseJsonBody(ServerRequestInterface $request): array
{
    // Try getParsedBody first
    $parsed = $request->getParsedBody();
    if (is_array($parsed)) {
        return $parsed;
    }

    // Fallback: parse manually (only reads once!)
    $contentType = $request->getHeaderLine('Content-Type');
    if (str_contains($contentType, 'application/json')) {
        $body = (string) $request->getBody();
        $decoded = json_decode($body, true);

        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return $decoded;
        }
    }

    return [];
}
```

## Why Not Make Streams Seekable?

Several approaches were considered:

### A) Buffer to php://temp (Rejected)
```php
$body = file_get_contents('php://input');
$stream = $streamFactory->createStream($body);
```
**Problem:** Buffers entire body in memory. Breaks streaming uploads.

### B) CachingStream wrapper (Rejected)
```php
class CachingStream {
    private $source; // php://input
    private $cache;  // php://temp
}
```
**Problem:** Complex, adds overhead, still buffers in memory.

### C) Use getParsedBody() (✅ Chosen)
- Standard PSR-7 pattern
- Framework parses once, caches result
- No memory overhead for large bodies
- Works with streaming uploads
- Explicit and clear in code

## Best Practices

1. **Always use `getParsedBody()`** for API endpoints accepting JSON/form data
2. **Add JsonBodyParser middleware** to routes that need it
3. **Never read `getBody()` multiple times** in the same request
4. **For raw body access** (webhooks, file uploads), read once and store:
   ```php
   $raw = (string) $request->getBody();
   $request = $request->withAttribute('raw_body', $raw);
   ```

## See Also

- PSR-7: https://www.php-fig.org/psr/psr-7/#16-uploaded-files
- PSR-15 Middleware: https://www.php-fig.org/psr/psr-15/
- JsonBodyParser: src/Middleware/JsonBodyParser.php
