<?php /** @noinspection PhpSameParameterValueInspection */

declare(strict_types=1);

namespace SharpAPI\Core\Client;

use Carbon\Carbon;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use SharpAPI\Core\DTO\SubscriptionInfo;
use SharpAPI\Core\DTO\SharpApiJob;
use SharpAPI\Core\Enums\SharpApiJobStatusEnum;
use SharpAPI\Core\Exceptions\ApiException;
use Spatie\Url\Url;

/**
 * Class SharpApiClient
 *
 * The main client for interacting with the SharpAPI service. This client provides
 * core functionalities such as sending requests, handling configurations, and
 * fetching basic information like ping status and subscription details.
 *
 * @package SharpApi\Core\Client
 * @api
 */
class SharpApiClient
{
    protected string $apiBaseUrl;
    protected string $apiKey;
    protected int $apiJobStatusPollingInterval = 10;
    protected bool $useCustomInterval = false;
    protected int $apiJobStatusPollingWait = 180;
    protected string $userAgent;
    protected ?int $rateLimitLimit = null;
    protected ?int $rateLimitRemaining = null;
    protected int $maxRetryOnRateLimit = 3;
    protected int $rateLimitLowThreshold = 3;
    private Client $client;

    /**
     * Initializes a new instance of the SharpApiClient class.
     *
     * @param string $apiKey The API key required for authentication.
     * @param string|null $apiBaseUrl Optional API base URL override.
     * @param string|null $userAgent Optional User-Agent header value.
     * @throws InvalidArgumentException if the API key is empty.
     */
    public function __construct(
        string $apiKey,
        ?string $apiBaseUrl = null,
        ?string $userAgent = null
    ) {
        $this->setApiKey($apiKey);
        if (empty($this->apiKey)) {
            throw new InvalidArgumentException('API key is required.');
        }
        $this->setApiBaseUrl($apiBaseUrl ?? 'https://sharpapi.com/api/v1');
        $this->setUserAgent($userAgent ?? 'SharpAPIPHPAgent/1.3.0');
        $this->client = new Client([
            'headers' => $this->getHeaders()
        ]);
    }

    public function getApiBaseUrl(): string
    {
        return $this->apiBaseUrl;
    }

    public function setApiBaseUrl(string $apiBaseUrl): void
    {
        $this->apiBaseUrl = $apiBaseUrl;
    }

    public function getApiKey(): string
    {
        return $this->apiKey;
    }

    public function setApiKey(string $apiKey): void
    {
        $this->apiKey = $apiKey;
    }

    public function getUserAgent(): string
    {
        return $this->userAgent;
    }

    public function setUserAgent(string $userAgent): void
    {
        $this->userAgent = $userAgent;
    }

    public function getApiJobStatusPollingInterval(): int
    {
        return $this->apiJobStatusPollingInterval;
    }

    /**
     * @api
     * @param int $apiJobStatusPollingInterval
     * @return void
     */
    public function setApiJobStatusPollingInterval(int $apiJobStatusPollingInterval): void
    {
        $this->apiJobStatusPollingInterval = $apiJobStatusPollingInterval;
    }

    public function isUseCustomInterval(): bool
    {
        return $this->useCustomInterval;
    }

    /**
     * @param bool $useCustomInterval
     * @return void
     * @api
     */
    public function setUseCustomInterval(bool $useCustomInterval): void
    {
        $this->useCustomInterval = $useCustomInterval;
    }

    public function getApiJobStatusPollingWait(): int
    {
        return $this->apiJobStatusPollingWait;
    }

    /**
     * @param int $apiJobStatusPollingWait
     * @return void
     * @api
     */
    public function setApiJobStatusPollingWait(int $apiJobStatusPollingWait): void
    {
        $this->apiJobStatusPollingWait = $apiJobStatusPollingWait;
    }

    /**
     * @return int|null The rate limit (requests per window) from the last API response, or null if not yet known.
     * @api
     */
    public function getRateLimitLimit(): ?int
    {
        return $this->rateLimitLimit;
    }

    /**
     * @return int|null The remaining requests in the current window from the last API response, or null if not yet known.
     * @api
     */
    public function getRateLimitRemaining(): ?int
    {
        return $this->rateLimitRemaining;
    }

    /**
     * @return int Maximum number of automatic retries on HTTP 429.
     * @api
     */
    public function getMaxRetryOnRateLimit(): int
    {
        return $this->maxRetryOnRateLimit;
    }

    /**
     * @param int $maxRetryOnRateLimit Maximum number of automatic retries on HTTP 429.
     * @return void
     * @api
     */
    public function setMaxRetryOnRateLimit(int $maxRetryOnRateLimit): void
    {
        $this->maxRetryOnRateLimit = $maxRetryOnRateLimit;
    }

    /**
     * @return int When remaining requests fall at or below this threshold, polling intervals are increased.
     * @api
     */
    public function getRateLimitLowThreshold(): int
    {
        return $this->rateLimitLowThreshold;
    }

    /**
     * @param int $rateLimitLowThreshold When remaining requests fall at or below this threshold, polling intervals are increased.
     * @return void
     * @api
     */
    public function setRateLimitLowThreshold(int $rateLimitLowThreshold): void
    {
        $this->rateLimitLowThreshold = $rateLimitLowThreshold;
    }

    /**
     * Sends a ping request to the API to check its availability and retrieve the current timestamp.
     *
     * @throws GuzzleException if the API request fails.
     * @api
     */
    public function ping(): ?array
    {
        $response = $this->makeRequest('GET', '/ping');
        return json_decode($response->getBody()->__toString(), true);
    }

    /**
     * Retrieves the subscription quota information.
     *
     * @return SubscriptionInfo|null A DTO containing subscription details.
     * @throws GuzzleException if the API request fails.
     * @throws Exception
     * @api
     */
    public function quota(): ?SubscriptionInfo
    {
        $response = $this->makeRequest('GET', '/quota');
        $info = json_decode($response->getBody()->__toString(), true);

        return new SubscriptionInfo(
            timestamp: new Carbon($info['timestamp']),
            on_trial: $info['on_trial'],
            trial_ends: new Carbon($info['trial_ends']),
            subscribed: $info['subscribed'],
            current_subscription_start: new Carbon($info['current_subscription_start']),
            current_subscription_end: new Carbon($info['current_subscription_end']),
            current_subscription_reset: new Carbon($info['current_subscription_reset']),
            subscription_words_quota: $info['subscription_words_quota'],
            subscription_words_used: $info['subscription_words_used'],
            subscription_words_used_percentage: $info['subscription_words_used_percentage'],
            requests_per_minute: $info['requests_per_minute']
        );
    }

    /**
     * Generic method to make an HTTP request using Guzzle.
     *
     * @param string $method The HTTP method (e.g., 'GET', 'POST').
     * @param string $url The API endpoint relative to the base URL.
     * @param array $data Optional request data for POST requests.
     * @param string|null $filePath Optional file path for file upload.
     * @return ResponseInterface The Guzzle response object.
     * @throws GuzzleException if the request fails.
     */
    protected function makeRequest(
        string $method,
        string $url,
        array $data = [],
        ?string $filePath = null
    ): ResponseInterface {
        $options = [
            'headers' => $this->getHeaders(),
        ];

        if ($method === 'POST') {
            if (is_string($filePath) && strlen($filePath)) {
                $multipart = [];

                // Attach file
                $multipart[] = [
                    'name'     => 'file',
                    'contents' => file_get_contents($filePath),
                    'filename' => basename($filePath),
                ];

                // Add each key-value pair from $data as a form-data field
                foreach ($data as $key => $value) {
                    $multipart[] = [
                        'name'     => $key,
                        'contents' => is_array($value) ? json_encode($value) : $value,
                    ];
                }

                $options['multipart'] = $multipart;
            } else {
                $options['json'] = $data;
            }
        }

        return $this->executeWithRateLimitRetry($method, $this->getApiBaseUrl() . $url, $options);
    }

    /**
     * Makes a GET request with proper query parameter handling.
     *
     * This method was added to properly support GET requests with query parameters
     * for utility endpoints (airports, web scraping, skills, job positions).
     * Unlike the generic makeRequest() method which only handles POST data,
     * this method correctly passes query parameters using Guzzle's 'query' option.
     *
     * @param string $url The API endpoint relative to the base URL.
     * @param array $queryParams Query parameters to append to the URL.
     * @return ResponseInterface The Guzzle response object.
     * @throws GuzzleException if the request fails.
     * @api
     */
    protected function makeGetRequest(
        string $url,
        array $queryParams = []
    ): ResponseInterface {
        $options = [
            'headers' => $this->getHeaders(),
        ];

        if (!empty($queryParams)) {
            $options['query'] = $queryParams;
        }

        return $this->executeWithRateLimitRetry('GET', $this->getApiBaseUrl() . $url, $options);
    }

    /**
     * @param ResponseInterface $response
     * @return mixed
     * @api
     */
    protected function parseStatusUrl(ResponseInterface $response): mixed
    {
        return json_decode($response->getBody()->__toString(), true)['status_url'];
    }

    /**
     * Polls the API for job results, waiting for the job status to be `SUCCESS` or `FAILED`.
     *
     * @param string $statusUrl The URL to check the job status.
     * @return SharpApiJob A DTO representing the completed job with its result.
     * @throws ApiException|GuzzleException if the job fails or polling times out.
     * @api
     */
    public function fetchResults(string $statusUrl): SharpApiJob
    {
        $waitingTime = 0;

        do {
            try {
                $response = $this->client->request('GET', $statusUrl, ['headers' => $this->getHeaders()]);
            } catch (ClientException $e) {
                if ($e->getResponse()->getStatusCode() === 429) {
                    $retryResponse = $e->getResponse();
                    $this->extractRateLimitHeaders($retryResponse);
                    $retryDelay = isset($retryResponse->getHeader('Retry-After')[0])
                        ? (int) $retryResponse->getHeader('Retry-After')[0]
                        : $this->getApiJobStatusPollingInterval();

                    $waitingTime += $retryDelay;
                    if ($waitingTime >= $this->getApiJobStatusPollingWait()) {
                        throw new ApiException('Polling timed out while waiting for job completion (rate limited).', 429);
                    }

                    sleep($retryDelay);
                    continue;
                }
                throw $e;
            }

            $this->extractRateLimitHeaders($response);
            $jobStatus = json_decode($response->getBody()->__toString(), true)['data']['attributes'];

            if ($jobStatus['status'] === SharpApiJobStatusEnum::SUCCESS->value ||
                $jobStatus['status'] === SharpApiJobStatusEnum::FAILED->value) {
                break;
            }

            $retryAfter = isset($response->getHeader('Retry-After')[0])
                ? (int)$response->getHeader('Retry-After')[0]
                : $this->getApiJobStatusPollingInterval();

            if ($this->isUseCustomInterval()) {
                $retryAfter = $this->getApiJobStatusPollingInterval();
            }

            $retryAfter = $this->adjustIntervalForRateLimit($retryAfter);

            $waitingTime += $retryAfter;
            if ($waitingTime >= $this->getApiJobStatusPollingWait()) {
                throw new ApiException('Polling timed out while waiting for job completion.');
            }

            sleep($retryAfter);
        } while (true);

        $data = json_decode($response->getBody()->__toString(), true)['data'];
        $url = Url::fromString($statusUrl);
        $result = count($url->getSegments()) == 5
            ? (object) json_decode($data['attributes']['result'])
            : (object) $data['attributes']['result'];

        return new SharpApiJob(
            id: $data['id'],
            type: $data['attributes']['type'],
            status: $data['attributes']['status'],
            result: $result ?? null
        );
    }

    /**
     * Extracts rate-limit headers from an API response and stores them.
     *
     * @param ResponseInterface $response The API response.
     */
    private function extractRateLimitHeaders(ResponseInterface $response): void
    {
        $limit = $response->getHeader('X-RateLimit-Limit');
        if (!empty($limit)) {
            $this->rateLimitLimit = (int) $limit[0];
        }

        $remaining = $response->getHeader('X-RateLimit-Remaining');
        if (!empty($remaining)) {
            $this->rateLimitRemaining = (int) $remaining[0];
        }
    }

    /**
     * Adjusts a polling interval when the rate-limit remaining count is low.
     *
     * When remaining is at or below the threshold, the base interval is multiplied
     * by a scaling factor that increases as remaining approaches 0.
     *
     * @param int $baseInterval The original polling interval in seconds.
     * @return int The adjusted interval.
     */
    private function adjustIntervalForRateLimit(int $baseInterval): int
    {
        if ($this->rateLimitRemaining === null || $this->rateLimitRemaining > $this->rateLimitLowThreshold) {
            return $baseInterval;
        }

        // Scale: 2x at threshold, increasing as remaining approaches 0
        $scale = 2 + ($this->rateLimitLowThreshold - $this->rateLimitRemaining);

        return $baseInterval * $scale;
    }

    /**
     * Wraps an HTTP request with automatic 429 retry logic.
     *
     * On success, rate-limit headers are extracted. On HTTP 429, the request is
     * retried after sleeping for the Retry-After duration, up to maxRetryOnRateLimit times.
     * All other client exceptions are re-thrown.
     *
     * @param string $method The HTTP method.
     * @param string $url The full request URL.
     * @param array $options Guzzle request options.
     * @return ResponseInterface The successful response.
     * @throws ApiException if retries are exhausted on 429.
     * @throws GuzzleException for non-429 failures.
     */
    private function executeWithRateLimitRetry(string $method, string $url, array $options): ResponseInterface
    {
        $attempts = 0;

        while (true) {
            try {
                $response = $this->client->request($method, $url, $options);
                $this->extractRateLimitHeaders($response);
                return $response;
            } catch (ClientException $e) {
                if ($e->getResponse()->getStatusCode() !== 429) {
                    throw $e;
                }

                $attempts++;
                $retryResponse = $e->getResponse();
                $this->extractRateLimitHeaders($retryResponse);

                if ($attempts >= $this->maxRetryOnRateLimit) {
                    throw new ApiException(
                        'Rate limit exceeded. Retries exhausted after ' . $attempts . ' attempts.',
                        429
                    );
                }

                $retryAfter = isset($retryResponse->getHeader('Retry-After')[0])
                    ? (int) $retryResponse->getHeader('Retry-After')[0]
                    : 1;

                sleep($retryAfter);
            }
        }
    }

    /**
     * Prepares the headers for API requests.
     *
     * @return array An associative array of headers.
     */
    private function getHeaders(): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->getApiKey(),
            'Accept' => 'application/json',
            'User-Agent' => $this->getUserAgent()
        ];
    }
}
