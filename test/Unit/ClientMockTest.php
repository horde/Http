<?php

declare(strict_types=1);

/**
 * Copyright 2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (BSD). If you
 * did not receive this file, see http://www.horde.org/licenses/bsd.
 *
 * @category   Horde
 * @package    Http
 * @subpackage UnitTests
 * @license    http://www.horde.org/licenses/bsd
 */

namespace Horde\Http\Test\Unit;

use Horde\Http\Client\Mock;
use Horde\Http\RequestFactory;
use OutOfBoundsException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the modern PSR-18 Mock HTTP client.
 *
 * Distinct from `MockTest`, which covers the legacy PSR-0
 * `Horde_Http_Request_Mock` class.
 */
#[CoversClass(Mock::class)]
class ClientMockTest extends TestCase
{
    public function testEmptyQueueThrowsOnSendRequest(): void
    {
        $mock = new Mock();
        $request = (new RequestFactory())->createRequest('GET', 'https://example.test/foo');

        $this->expectException(OutOfBoundsException::class);
        $mock->sendRequest($request);
    }

    public function testSingleQueuedResponseIsReturnedRepeatedly(): void
    {
        $mock = new Mock();
        $mock->addResponse('BODY', 200);

        $rf = new RequestFactory();
        $r1 = $mock->sendRequest($rf->createRequest('GET', 'https://example.test/a'));
        $r2 = $mock->sendRequest($rf->createRequest('GET', 'https://example.test/b'));

        $this->assertSame(200, $r1->getStatusCode());
        $this->assertSame(200, $r2->getStatusCode());
    }

    public function testMultipleQueuedResponsesAreConsumedInOrder(): void
    {
        $mock = new Mock();
        $mock->addResponse('FIRST', 200);
        $mock->addResponse('SECOND', 200);
        $mock->addResponse('THIRD', 200);

        $rf = new RequestFactory();
        $r1 = $mock->sendRequest($rf->createRequest('GET', 'https://example.test/1'));
        $r2 = $mock->sendRequest($rf->createRequest('GET', 'https://example.test/2'));
        $r3 = $mock->sendRequest($rf->createRequest('GET', 'https://example.test/3'));

        $this->assertSame('FIRST', (string) $r1->getBody());
        $this->assertSame('SECOND', (string) $r2->getBody());
        $this->assertSame('THIRD', (string) $r3->getBody());
    }

    public function testGetRequestsRecordsEveryCall(): void
    {
        $mock = new Mock();
        $mock->addResponse('BODY', 200);

        $rf = new RequestFactory();
        $req1 = $rf->createRequest('GET', 'https://example.test/foo');
        $req2 = $rf->createRequest('POST', 'https://example.test/bar');
        $mock->sendRequest($req1);
        $mock->sendRequest($req2);

        $recorded = $mock->getRequests();
        $this->assertCount(2, $recorded);
        $this->assertSame($req1, $recorded[0]);
        $this->assertSame($req2, $recorded[1]);
    }

    public function testGetRequestCount(): void
    {
        $mock = new Mock();
        $mock->addResponse('BODY', 200);

        $rf = new RequestFactory();
        $this->assertSame(0, $mock->getRequestCount());
        $mock->sendRequest($rf->createRequest('GET', 'https://example.test/foo'));
        $this->assertSame(1, $mock->getRequestCount());
        $mock->sendRequest($rf->createRequest('GET', 'https://example.test/bar'));
        $this->assertSame(2, $mock->getRequestCount());
    }

    public function testGetRequestedUrls(): void
    {
        $mock = new Mock();
        $mock->addResponse('BODY', 200);

        $rf = new RequestFactory();
        $mock->sendRequest($rf->createRequest('GET', 'https://example.test/a?x=1'));
        $mock->sendRequest($rf->createRequest('POST', 'https://example.test/b'));

        $this->assertSame(
            ['https://example.test/a?x=1', 'https://example.test/b'],
            $mock->getRequestedUrls(),
        );
    }

    public function testGetRequestByIndex(): void
    {
        $mock = new Mock();
        $mock->addResponse('BODY', 200);

        $rf = new RequestFactory();
        $req1 = $rf->createRequest('GET', 'https://example.test/foo');
        $req2 = $rf->createRequest('GET', 'https://example.test/bar');
        $mock->sendRequest($req1);
        $mock->sendRequest($req2);

        $this->assertSame($req1, $mock->getRequest(0));
        $this->assertSame($req2, $mock->getRequest(1));
        $this->assertNull($mock->getRequest(2));
        $this->assertNull($mock->getRequest(-1));
    }

    public function testRecordingSurvivesThrownOutOfBounds(): void
    {
        // The recording happens BEFORE the empty-queue check, so a caller
        // can still inspect what was requested even when no response was
        // queued. This lets tests assert "the code under test made this
        // call" separately from "the mock had an answer for it."
        $mock = new Mock();
        $rf = new RequestFactory();

        try {
            $mock->sendRequest($rf->createRequest('GET', 'https://example.test/missing'));
            $this->fail('expected OutOfBoundsException');
        } catch (OutOfBoundsException) {
            // Expected.
        }

        $this->assertSame(1, $mock->getRequestCount());
        $this->assertSame('https://example.test/missing', $mock->getRequestedUrls()[0]);
    }

    public function testClearRequestsWipesHistoryButKeepsResponseQueue(): void
    {
        $mock = new Mock();
        $mock->addResponse('BODY', 200);
        $rf = new RequestFactory();
        $mock->sendRequest($rf->createRequest('GET', 'https://example.test/foo'));

        $this->assertSame(1, $mock->getRequestCount());

        $mock->clearRequests();
        $this->assertSame(0, $mock->getRequestCount());
        $this->assertSame([], $mock->getRequests());

        // The response queue is untouched, so a subsequent call still
        // returns the previously-queued response.
        $r = $mock->sendRequest($rf->createRequest('GET', 'https://example.test/bar'));
        $this->assertSame(200, $r->getStatusCode());
        $this->assertSame(1, $mock->getRequestCount());
    }

    public function testRecordedRequestsPreserveHeaders(): void
    {
        $mock = new Mock();
        $mock->addResponse('BODY', 200);

        $rf = new RequestFactory();
        $req = $rf->createRequest('GET', 'https://example.test/foo')
            ->withHeader('User-Agent', 'my-app/1.0')
            ->withHeader('Accept', 'application/json');
        $mock->sendRequest($req);

        $recorded = $mock->getRequest(0);
        $this->assertNotNull($recorded);
        $this->assertSame('my-app/1.0', $recorded->getHeaderLine('User-Agent'));
        $this->assertSame('application/json', $recorded->getHeaderLine('Accept'));
    }
}
