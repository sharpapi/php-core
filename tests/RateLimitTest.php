<?php

declare(strict_types=1);

namespace SharpAPI\Core\Tests;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use SharpAPI\Core\Exceptions\ApiException;

class RateLimitTest extends TestCase
{
    private function createClient(MockHandler $mock): TestableSharpApiClient
    {
        $handler = HandlerStack::create($mock);
        $guzzle = new Client(['handler' => $handler]);
        return new TestableSharpApiClient('test-api-key', $guzzle);
    }

    private function jsonBody(array $data): string
    {
        return json_encode($data);
    }

    private function rateLimitHeaders(int $limit = 100, int $remaining = 99): array
    {
        return [
            'X-RateLimit-Limit' => [(string) $limit],
            'X-RateLimit-Remaining' => [(string) $remaining],
        ];
    }

    // -----------------------------------------------------------------------
    // extractRateLimitHeaders tests
    // -----------------------------------------------------------------------

    public function testRateLimitHeadersNullBeforeAnyCall(): void
    {
        $mock = new MockHandler([]);
        $client = $this->createClient($mock);

        $this->assertNull($client->getRateLimitLimit());
        $this->assertNull($client->getRateLimitRemaining());
    }

    public function testRateLimitHeadersPopulatedAfterSuccessfulRequest(): void
    {
        $mock = new MockHandler([
            new Response(200, $this->rateLimitHeaders(60, 58), $this->jsonBody(['ping' => 'pong'])),
        ]);
        $client = $this->createClient($mock);

        $client->ping();

        $this->assertSame(60, $client->getRateLimitLimit());
        $this->assertSame(58, $client->getRateLimitRemaining());
    }

    public function testRateLimitHeadersUpdateAcrossMultipleCalls(): void
    {
        $mock = new MockHandler([
            new Response(200, $this->rateLimitHeaders(100, 50), $this->jsonBody(['ping' => 'pong'])),
            new Response(200, $this->rateLimitHeaders(100, 49), $this->jsonBody(['ping' => 'pong'])),
        ]);
        $client = $this->createClient($mock);

        $client->ping();
        $this->assertSame(50, $client->getRateLimitRemaining());

        $client->ping();
        $this->assertSame(49, $client->getRateLimitRemaining());
    }

    public function testRateLimitHeadersMissingLeavesValuesNull(): void
    {
        $mock = new MockHandler([
            new Response(200, [], $this->jsonBody(['ping' => 'pong'])),
        ]);
        $client = $this->createClient($mock);

        $client->ping();

        $this->assertNull($client->getRateLimitLimit());
        $this->assertNull($client->getRateLimitRemaining());
    }

    public function testPartialRateLimitHeadersOnlyLimitPresent(): void
    {
        $mock = new MockHandler([
            new Response(200, ['X-RateLimit-Limit' => ['100']], $this->jsonBody(['ping' => 'pong'])),
        ]);
        $client = $this->createClient($mock);

        $client->ping();

        $this->assertSame(100, $client->getRateLimitLimit());
        $this->assertNull($client->getRateLimitRemaining());
    }

    // -----------------------------------------------------------------------
    // 429 retry logic tests
    // -----------------------------------------------------------------------

    public function testRetryOn429ThenSucceed(): void
    {
        $mock = new MockHandler([
            new Response(429, array_merge($this->rateLimitHeaders(100, 0), ['Retry-After' => ['0']]), ''),
            new Response(200, $this->rateLimitHeaders(100, 99), $this->jsonBody(['ping' => 'pong'])),
        ]);
        $client = $this->createClient($mock);

        $result = $client->ping();

        $this->assertSame(['ping' => 'pong'], $result);
        $this->assertSame(99, $client->getRateLimitRemaining());
    }

    public function testRetryExhaustedThrowsApiException(): void
    {
        // Default maxRetryOnRateLimit is 3, so 3 x 429 should exhaust retries
        $mock = new MockHandler([
            new Response(429, ['Retry-After' => ['0']], ''),
            new Response(429, ['Retry-After' => ['0']], ''),
            new Response(429, ['Retry-After' => ['0']], ''),
        ]);
        $client = $this->createClient($mock);

        $this->expectException(ApiException::class);
        $this->expectExceptionCode(429);
        $this->expectExceptionMessageMatches('/Rate limit exceeded.*3 attempts/');

        $client->ping();
    }

    public function testCustomMaxRetryOnRateLimit(): void
    {
        // Set max retries to 1: first 429 attempt, then exhausted
        $mock = new MockHandler([
            new Response(429, ['Retry-After' => ['0']], ''),
        ]);
        $client = $this->createClient($mock);
        $client->setMaxRetryOnRateLimit(1);

        $this->expectException(ApiException::class);
        $this->expectExceptionCode(429);
        $this->expectExceptionMessageMatches('/1 attempts/');

        $client->ping();
    }

    public function testMultiple429sBeforeSuccess(): void
    {
        $mock = new MockHandler([
            new Response(429, ['Retry-After' => ['0']], ''),
            new Response(429, ['Retry-After' => ['0']], ''),
            new Response(200, $this->rateLimitHeaders(100, 97), $this->jsonBody(['ping' => 'pong'])),
        ]);
        $client = $this->createClient($mock);

        $result = $client->ping();

        $this->assertSame(['ping' => 'pong'], $result);
        $this->assertSame(97, $client->getRateLimitRemaining());
    }

    public function testNon429ClientExceptionIsNotRetried(): void
    {
        $mock = new MockHandler([
            new Response(403, [], $this->jsonBody(['error' => 'Forbidden'])),
        ]);
        $client = $this->createClient($mock);

        $this->expectException(\GuzzleHttp\Exception\ClientException::class);

        $client->ping();
    }

    public function testRateLimitHeadersExtractedFrom429Response(): void
    {
        $mock = new MockHandler([
            new Response(429, array_merge($this->rateLimitHeaders(100, 0), ['Retry-After' => ['0']]), ''),
            new Response(200, $this->rateLimitHeaders(100, 100), $this->jsonBody(['ping' => 'pong'])),
        ]);
        $client = $this->createClient($mock);

        $client->ping();

        // After success, headers from the 200 response should be stored
        $this->assertSame(100, $client->getRateLimitLimit());
        $this->assertSame(100, $client->getRateLimitRemaining());
    }

    // -----------------------------------------------------------------------
    // makeGetRequest path (used by utility endpoints)
    // -----------------------------------------------------------------------

    public function testMakeGetRequestExtractsRateLimitHeaders(): void
    {
        $mock = new MockHandler([
            new Response(200, $this->rateLimitHeaders(60, 42), $this->jsonBody(['data' => []])),
        ]);
        $client = $this->createClient($mock);

        $client->testMakeGetRequest('/airports', ['per_page' => 5]);

        $this->assertSame(60, $client->getRateLimitLimit());
        $this->assertSame(42, $client->getRateLimitRemaining());
    }

    public function testMakeGetRequestRetries429(): void
    {
        $mock = new MockHandler([
            new Response(429, ['Retry-After' => ['0']], ''),
            new Response(200, $this->rateLimitHeaders(60, 59), $this->jsonBody(['data' => []])),
        ]);
        $client = $this->createClient($mock);

        $response = $client->testMakeGetRequest('/airports');
        $body = json_decode($response->getBody()->__toString(), true);

        $this->assertSame(['data' => []], $body);
        $this->assertSame(59, $client->getRateLimitRemaining());
    }

    // -----------------------------------------------------------------------
    // Getter/setter round-trip tests
    // -----------------------------------------------------------------------

    public function testMaxRetryOnRateLimitGetterSetter(): void
    {
        $mock = new MockHandler([]);
        $client = $this->createClient($mock);

        $this->assertSame(3, $client->getMaxRetryOnRateLimit());

        $client->setMaxRetryOnRateLimit(7);
        $this->assertSame(7, $client->getMaxRetryOnRateLimit());
    }

    public function testRateLimitLowThresholdGetterSetter(): void
    {
        $mock = new MockHandler([]);
        $client = $this->createClient($mock);

        $this->assertSame(3, $client->getRateLimitLowThreshold());

        $client->setRateLimitLowThreshold(15);
        $this->assertSame(15, $client->getRateLimitLowThreshold());
    }

    // -----------------------------------------------------------------------
    // adjustIntervalForRateLimit (tested via fetchResults polling)
    // -----------------------------------------------------------------------

    public function testAdaptivePollingScalesIntervalWhenRemainingLow(): void
    {
        // Use reflection to test the private adjustIntervalForRateLimit method directly
        $mock = new MockHandler([]);
        $client = $this->createClient($mock);
        $client->setRateLimitLowThreshold(5);

        $ref = new \ReflectionClass(\SharpAPI\Core\Client\SharpApiClient::class);

        // Set rateLimitRemaining to 3 (below threshold of 5)
        $remainingProp = $ref->getProperty('rateLimitRemaining');
        $remainingProp->setAccessible(true);
        $remainingProp->setValue($client, 3);

        $method = $ref->getMethod('adjustIntervalForRateLimit');
        $method->setAccessible(true);

        // Scale = 2 + (threshold - remaining) = 2 + (5 - 3) = 4
        // Result = baseInterval * 4 = 10 * 4 = 40
        $result = $method->invoke($client, 10);
        $this->assertSame(40, $result);
    }

    public function testAdaptivePollingNoScaleWhenRemainingAboveThreshold(): void
    {
        $mock = new MockHandler([]);
        $client = $this->createClient($mock);
        $client->setRateLimitLowThreshold(5);

        $ref = new \ReflectionClass(\SharpAPI\Core\Client\SharpApiClient::class);

        $remainingProp = $ref->getProperty('rateLimitRemaining');
        $remainingProp->setAccessible(true);
        $remainingProp->setValue($client, 50);

        $method = $ref->getMethod('adjustIntervalForRateLimit');
        $method->setAccessible(true);

        // remaining (50) > threshold (5), so no scaling
        $result = $method->invoke($client, 10);
        $this->assertSame(10, $result);
    }

    public function testAdaptivePollingNoScaleWhenRemainingIsNull(): void
    {
        $mock = new MockHandler([]);
        $client = $this->createClient($mock);
        $client->setRateLimitLowThreshold(5);

        $ref = new \ReflectionClass(\SharpAPI\Core\Client\SharpApiClient::class);
        $method = $ref->getMethod('adjustIntervalForRateLimit');
        $method->setAccessible(true);

        // remaining is null by default, so no scaling
        $result = $method->invoke($client, 10);
        $this->assertSame(10, $result);
    }

    public function testAdaptivePollingAtExactThreshold(): void
    {
        $mock = new MockHandler([]);
        $client = $this->createClient($mock);
        $client->setRateLimitLowThreshold(5);

        $ref = new \ReflectionClass(\SharpAPI\Core\Client\SharpApiClient::class);

        $remainingProp = $ref->getProperty('rateLimitRemaining');
        $remainingProp->setAccessible(true);
        $remainingProp->setValue($client, 5);

        $method = $ref->getMethod('adjustIntervalForRateLimit');
        $method->setAccessible(true);

        // At threshold: scale = 2 + (5 - 5) = 2
        // Result = 10 * 2 = 20
        $result = $method->invoke($client, 10);
        $this->assertSame(20, $result);
    }

    public function testAdaptivePollingAtZeroRemaining(): void
    {
        $mock = new MockHandler([]);
        $client = $this->createClient($mock);
        $client->setRateLimitLowThreshold(5);

        $ref = new \ReflectionClass(\SharpAPI\Core\Client\SharpApiClient::class);

        $remainingProp = $ref->getProperty('rateLimitRemaining');
        $remainingProp->setAccessible(true);
        $remainingProp->setValue($client, 0);

        $method = $ref->getMethod('adjustIntervalForRateLimit');
        $method->setAccessible(true);

        // At zero: scale = 2 + (5 - 0) = 7
        // Result = 10 * 7 = 70
        $result = $method->invoke($client, 10);
        $this->assertSame(70, $result);
    }

    // -----------------------------------------------------------------------
    // fetchResults 429 handling in polling loop
    // -----------------------------------------------------------------------

    public function testFetchResults429RetryInPollingLoop(): void
    {
        // First: make a successful request to establish client
        // Then test fetchResults polling with a 429 mid-poll
        $mock = new MockHandler([
            // fetchResults first poll: 429
            new Response(429, array_merge($this->rateLimitHeaders(100, 0), ['Retry-After' => ['0']]), ''),
            // fetchResults retry: pending
            new Response(200, $this->rateLimitHeaders(100, 99), $this->jsonBody([
                'data' => [
                    'id' => 'job-123',
                    'attributes' => [
                        'type' => 'test',
                        'status' => 'success',
                        'result' => json_encode(['pass' => true]),
                    ],
                ],
            ])),
        ]);
        $client = $this->createClient($mock);
        $client->setApiJobStatusPollingInterval(0);

        $result = $client->fetchResults('https://sharpapi.com/api/v1/dispatch/job/job-123/result');

        $this->assertSame('job-123', $result->id);
        $this->assertSame('success', $result->status);
    }

    public function testFetchResultsPollingTimeout(): void
    {
        // Return "pending" every time — the client should eventually time out
        $mock = new MockHandler([
            new Response(200, ['Retry-After' => ['0']], $this->jsonBody([
                'data' => ['id' => 'job-1', 'attributes' => ['type' => 'test', 'status' => 'pending', 'result' => null]],
            ])),
        ]);
        $client = $this->createClient($mock);
        $client->setApiJobStatusPollingInterval(0);
        $client->setApiJobStatusPollingWait(0); // immediate timeout

        $this->expectException(ApiException::class);
        $this->expectExceptionMessageMatches('/timed out/i');

        $client->fetchResults('https://sharpapi.com/api/v1/dispatch/job/job-1/result');
    }

    // -----------------------------------------------------------------------
    // User-Agent version check
    // -----------------------------------------------------------------------

    public function testDefaultUserAgentContainsVersion130(): void
    {
        $mock = new MockHandler([]);
        $client = $this->createClient($mock);

        $this->assertStringContainsString('1.3.0', $client->getUserAgent());
    }
}
