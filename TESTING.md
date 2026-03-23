# Testing Horde_Http

## Test Suites

The test suite follows PSR-4 structure. It is divided into two types:

### Unit Tests (test/Unit/)
Fast and isolated tests which don't make real HTTP calls. Run these during development:

```bash
phpunit --testsuite="horde/http unit tests"
```

Time: ~0.4 seconds, 130 tests (includes mock client tests)

### Live Integration Tests (test/Live/)
Slow tests that make real HTTP calls to external servers. Run these as needed to debug specific issues:

```bash
phpunit --testsuite="horde/http live integration tests"
```

Time: ~10+ seconds (network dependent), 24 tests

## Running All Tests

```bash
# Default: runs unit tests only (fast ~0.3s)
phpunit

# Run live integration tests
phpunit --testsuite="horde/http live integration tests"

# Run all tests (unit + live)
phpunit --testsuite="horde/http unit tests" --testsuite="horde/http live integration tests"
```

## Test Configuration

Integration tests use horde.org by default. Set the `HTTP_TEST_CONFIG` environment variable for a different target:

```bash
export HTTP_TEST_CONFIG='{"http":{"server":"example.com"}}'
phpunit --testsuite="horde/http live integration tests"
```

## Test Structure

Tests use PSR-4 autoloading:
- test/Unit/ => Horde\Http\Test\Unit\
- test/Live/ => Horde\Http\Test\Live\

No bootstrap file needed - phpunit.xml.dist directly uses `vendor/autoload.php`.
