<?php

declare(strict_types=1);

namespace SharpAPI\Core\Tests;

use GuzzleHttp\Client;
use ReflectionClass;
use SharpAPI\Core\Client\SharpApiClient;

/**
 * Subclass that allows injecting a mock Guzzle client for unit testing.
 */
class TestableSharpApiClient extends SharpApiClient
{
    public function __construct(string $apiKey, Client $mockClient)
    {
        parent::__construct($apiKey);
        // Replace the private Guzzle client via reflection
        $ref = new ReflectionClass(SharpApiClient::class);
        $prop = $ref->getProperty('client');
        $prop->setAccessible(true);
        $prop->setValue($this, $mockClient);
    }

    /**
     * Expose makeRequest for testing.
     */
    public function testMakeRequest(
        string $method,
        string $url,
        array $data = [],
        ?string $filePath = null
    ): \Psr\Http\Message\ResponseInterface {
        return $this->makeRequest($method, $url, $data, $filePath);
    }

    /**
     * Expose makeGetRequest for testing.
     */
    public function testMakeGetRequest(
        string $url,
        array $queryParams = []
    ): \Psr\Http\Message\ResponseInterface {
        return $this->makeGetRequest($url, $queryParams);
    }
}
