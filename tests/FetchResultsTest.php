<?php

declare(strict_types=1);

namespace SharpAPI\Core\Tests;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use SharpAPI\Core\Client\SharpApiClient;
use SharpAPI\Core\DTO\SharpApiJob;

class FetchResultsTest extends TestCase
{
    // Generic status URL, 5 path segments: the result arrives as a JSON-encoded string
    private const STATUS_URL_5 = 'https://sharpapi.com/api/v1/job/status/abc-123';

    // Endpoint-specific status URL, 7 path segments: the result arrives already decoded
    private const STATUS_URL_7 = 'https://sharpapi.com/api/v1/content/translate/job/status/abc-123';

    private function createClient(MockHandler $mock): TestableSharpApiClient
    {
        $guzzle = new Client(['handler' => HandlerStack::create($mock)]);

        return new TestableSharpApiClient('test-api-key', $guzzle);
    }

    private function jobResponse(string $status, mixed $result, array $headers = []): Response
    {
        return new Response(200, $headers, json_encode([
            'data' => [
                'id' => 'abc-123',
                'type' => 'api_job',
                'attributes' => [
                    'status' => $status,
                    'type' => 'content_translate',
                    'result' => $result,
                ],
            ],
        ]));
    }

    public function testFailedJobWithNullResultOnFiveSegmentUrlReturnsJob(): void
    {
        $client = $this->createClient(new MockHandler([$this->jobResponse('failed', null)]));

        $job = $client->fetchResults(self::STATUS_URL_5);

        $this->assertInstanceOf(SharpApiJob::class, $job);
        $this->assertSame('failed', $job->status);
        $this->assertSame('abc-123', $job->id);
    }

    public function testFailedJobWithNullResultOnEndpointSpecificUrlReturnsJob(): void
    {
        $client = $this->createClient(new MockHandler([$this->jobResponse('failed', null)]));

        $job = $client->fetchResults(self::STATUS_URL_7);

        $this->assertSame('failed', $job->status);
    }

    public function testNullResultIsHandledTheSameForBothUrlShapes(): void
    {
        $client = $this->createClient(new MockHandler([
            $this->jobResponse('failed', null),
            $this->jobResponse('failed', null),
        ]));

        $five = $client->fetchResults(self::STATUS_URL_5);
        $seven = $client->fetchResults(self::STATUS_URL_7);

        $this->assertEquals($seven->result, $five->result);
    }

    public function testSuccessfulJobDecodesJsonStringResultOnFiveSegmentUrl(): void
    {
        $client = $this->createClient(new MockHandler([
            $this->jobResponse('success', json_encode(['content' => 'Hallo', 'to_language' => 'German'])),
        ]));

        $job = $client->fetchResults(self::STATUS_URL_5);

        $this->assertSame('success', $job->status);
        $this->assertSame('Hallo', $job->result->content);
        $this->assertSame(['content' => 'Hallo', 'to_language' => 'German'], $job->getResultArray());
    }

    public function testSuccessfulJobKeepsObjectResultOnEndpointSpecificUrl(): void
    {
        $client = $this->createClient(new MockHandler([
            $this->jobResponse('success', ['content' => 'Hallo']),
        ]));

        $job = $client->fetchResults(self::STATUS_URL_7);

        $this->assertSame('Hallo', $job->result->content);
    }

    public function testAlreadyDecodedResultOnFiveSegmentUrlDoesNotThrow(): void
    {
        $client = $this->createClient(new MockHandler([
            $this->jobResponse('success', ['content' => 'Hallo']),
        ]));

        $job = $client->fetchResults(self::STATUS_URL_5);

        $this->assertSame('Hallo', $job->result->content);
    }

    public function testCustomPollingIntervalOverridesRetryAfterHeader(): void
    {
        $client = $this->createClient(new MockHandler([
            $this->jobResponse('pending', null, ['Retry-After' => ['30']]),
            $this->jobResponse('success', json_encode(['content' => 'done'])),
        ]));
        $client->setRequestsPerMinute(0);
        $client->setApiJobStatusPollingWait(5);
        $client->setApiJobStatusPollingInterval(0);
        $client->setUseCustomInterval(true);

        // With Retry-After (30s) honoured this would time out against the 5s wait budget.
        $job = $client->fetchResults(self::STATUS_URL_5);

        $this->assertSame('success', $job->status);
    }

    public function testMissingApiKeyThrowsInvalidArgumentException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('SharpAPI API key is missing');

        new SharpApiClient(null);
    }

    public function testEmptyApiKeyThrowsInvalidArgumentException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('SharpAPI API key is missing');

        new SharpApiClient('');
    }

    public function testGetResultArrayIsShallow(): void
    {
        $client = $this->createClient(new MockHandler([
            $this->jobResponse('success', json_encode(['meta' => ['lang' => 'de']])),
        ]));

        $job = $client->fetchResults(self::STATUS_URL_5);

        // Documented behaviour: nested objects are not converted.
        $this->assertInstanceOf(\stdClass::class, $job->getResultArray()['meta']);
        $this->assertSame(['meta' => ['lang' => 'de']], json_decode($job->getResultJson(), true));
    }
}
