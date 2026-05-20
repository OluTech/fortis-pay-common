<?php

namespace Fortis\Api\Http;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;

/**
 * PSR-18 HTTP client wrapper for Fortis API calls.
 *
 * Handles authentication headers, base URL resolution, JSON encoding/decoding,
 * retry logic, and standardized ApiResponse conversion.
 */
class FortisHttpClient
{
    public function __construct(
        private ClientInterface $httpClient,
        private RequestFactoryInterface $requestFactory,
        private StreamFactoryInterface $streamFactory,
        private LoggerInterface $logger,
        private ?RetryHandler $retryHandler = null,
    ) {
    }

    /**
     * Send a single API request with authentication, retry, and response normalization.
     *
     * @param string $method HTTP method (GET, POST, PUT, PATCH, DELETE)
     * @param string $url Full URL to call
     * @param array $headers HTTP headers (auth headers, content-type, etc.)
     * @param array $data Request body data (will be JSON-encoded)
     *
     * @return ApiResponse
     */
    public function request(string $method, string $url, array $headers, array $data = []): ApiResponse
    {
        $operation = function () use ($method, $url, $headers, $data): ApiResponse {
            return $this->doRequest($method, $url, $headers, $data);
        };

        if ($this->retryHandler !== null) {
            return $this->retryHandler->execute($operation, $this->logger);
        }

        return $operation();
    }

    /**
     * Send multiple independent API requests as a batch.
     *
     * Attempts to use async/promise-based execution if Guzzle is detected.
     * Falls back to sequential execution for other HTTP clients.
     *
     * @param array $requests Array of requests, each with keys: method, url, headers, data
     * @param bool $async Enable async execution if supported (default: true)
     *
     * @return ApiResponse[] Array of responses keyed the same as input
     */
    public function batch(array $requests, bool $async = true): array
    {
        if ($async && $this->isGuzzleClient()) {
            try {
                return $this->asyncBatch($requests);
            } catch (\Throwable $e) {
                $this->logger->warning('Async batch failed, falling back to sequential', [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $this->sequentialBatch($requests);
    }

    /**
     * Sequential batch execution (blocking).
     *
     * @param array $requests
     *
     * @return ApiResponse[]
     */
    private function sequentialBatch(array $requests): array
    {
        $responses = [];
        foreach ($requests as $key => $req) {
            $responses[$key] = $this->request(
                $req['method'],
                $req['url'],
                $req['headers'],
                $req['data'] ?? []
            );
        }

        return $responses;
    }

    /**
     * Async batch execution using Guzzle promises.
     *
     * @param array $requests
     *
     * @return ApiResponse[]
     */
    private function asyncBatch(array $requests): array
    {
        $promises = [];

        foreach ($requests as $key => $req) {
            $promises[$key] = $this->createAsyncRequest(
                $req['method'],
                $req['url'],
                $req['headers'],
                $req['data'] ?? []
            );
        }

        return $this->waitForAllPromises($promises);
    }

    /**
     * Create an async request promise.
     *
     * @param string $method HTTP method (GET, POST, PUT, PATCH, DELETE)
     * @param string $url Full URL to request
     * @param array $headers HTTP headers
     * @param array $data Request body data
     *
     * @return mixed GuzzleHttp Promise that resolves to ApiResponse
     *
     * @throws \RuntimeException If HTTP client does not support async
     * @throws \Psr\Http\Message\InvalidArgumentException If headers are invalid (from PSR-7)
     * @throws \JsonException If request data cannot be JSON encoded
     * @throws \Throwable Any exception from PSR-18 client sendAsync()
     */
    private function createAsyncRequest(string $method, string $url, array $headers, array $data): mixed
    {
        if (!method_exists($this->httpClient, 'sendAsync')) {
            throw new \RuntimeException('HTTP client does not support async');
        }

        $request = $this->requestFactory->createRequest($method, $url);

        foreach ($headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        if (!empty($data) && in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
            try {
                $jsonBody = json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
            } catch (\JsonException $e) {
                $this->logger->error('Failed to JSON encode request data', [
                    'error'  => $e->getMessage(),
                    'method' => $method,
                    'url'    => $url,
                ]);

                throw new \RuntimeException('Failed to JSON encode request data: ' . $e->getMessage(), 0, $e);
            }

            $body    = $this->streamFactory->createStream($jsonBody);
            $request = $request->withBody($body);
        }

        return $this->httpClient->sendAsync($request)->then(
            function ($response) use ($url, $method) {
                return $this->convertResponseToApiResponse($response, $url, $method);
            },
            function (\Throwable $e) use ($url, $method) {
                $this->logger->error('Async request failed', [
                    'url'    => $url,
                    'method' => $method,
                    'error'  => $e->getMessage(),
                ]);

                return ApiResponse::error(0, 'Request failed: ' . $e->getMessage());
            }
        );
    }

    /**
     * Wait for all promises and collect responses.
     *
     * @param array $promises Array of GuzzleHttp promises
     *
     * @return ApiResponse[] Array of responses keyed by original request key
     *
     * @throws \RuntimeException If GuzzleHttp Promise Utils not available
     */
    private function waitForAllPromises(array $promises): array
    {
        if (!class_exists('GuzzleHttp\Promise\Utils')) {
            throw new \RuntimeException('GuzzleHttp Promise Utils not available');
        }

        try {
            $settled   = \GuzzleHttp\Promise\Utils::settle($promises)->wait();
            $responses = [];

            foreach ($settled as $key => $result) {
                if ($result['state'] === 'fulfilled') {
                    $responses[$key] = $result['value'];
                } else {
                    $reason          = $result['reason'] ?? 'Unknown error';
                    $message         = $reason instanceof \Throwable ? $reason->getMessage() : (string)$reason;
                    $responses[$key] = ApiResponse::error(0, "Promise rejected: $message");
                }
            }

            return $responses;
        } catch (\Throwable $e) {
            $this->logger->error('Promise settlement failed', [
                'error'           => $e->getMessage(),
                'promise_count'   => count($promises),
                'exception_class' => get_class($e),
            ]);

            // Return error responses for each promise instead of crashing
            $responses = [];
            foreach ($promises as $key => $_) {
                $responses[$key] = ApiResponse::error(0, 'Promise settlement failed: ' . $e->getMessage());
            }

            return $responses;
        }
    }

    /**
     * Convert PSR-7 response to ApiResponse.
     *
     * @param object $response
     * @param string $url
     * @param string $method
     *
     * @return ApiResponse
     */
    private function convertResponseToApiResponse($response, string $url, string $method): ApiResponse
    {
        $statusCode = $response->getStatusCode();
        $rawBody    = (string)$response->getBody();
        $decoded    = json_decode($rawBody, true);

        if ($statusCode >= 200 && $statusCode < 300) {
            if (is_array($decoded) && isset($decoded['type']) && $decoded['type'] === 'Error') {
                return ApiResponse::error(
                    $statusCode,
                    $decoded['detail'] ?? $decoded['title'] ?? 'API error',
                    $decoded['meta']['errors'] ?? null,
                    $rawBody
                );
            }

            return ApiResponse::success($statusCode, $decoded, $rawBody);
        }

        $message = 'HTTP error';
        if (is_array($decoded)) {
            $message = $decoded['detail'] ?? $decoded['title'] ?? $decoded['message'] ?? 'HTTP error';
        }

        return ApiResponse::error($statusCode, $message, null, $rawBody);
    }

    /**
     * Check if the HTTP client is GuzzleHttp (supports async).
     *
     * @return bool
     */
    private function isGuzzleClient(): bool
    {
        $class = get_class($this->httpClient);

        return strpos($class, 'GuzzleHttp') !== false || method_exists($this->httpClient, 'sendAsync');
    }

    private function doRequest(string $method, string $url, array $headers, array $data): ApiResponse
    {
        try {
            $request = $this->requestFactory->createRequest($method, $url);

            foreach ($headers as $name => $value) {
                $request = $request->withHeader($name, $value);
            }

            if (!empty($data) && in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
                try {
                    $jsonBody = json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
                } catch (\JsonException $e) {
                    $this->logger->error('Failed to JSON encode request data', [
                        'url'    => $url,
                        'method' => $method,
                        'error'  => $e->getMessage(),
                    ]);

                    throw new \RuntimeException('Failed to JSON encode request data: ' . $e->getMessage(), 0, $e);
                }

                $body    = $this->streamFactory->createStream($jsonBody);
                $request = $request->withBody($body);
            }

            $response   = $this->httpClient->sendRequest($request);
            $statusCode = $response->getStatusCode();
            $rawBody    = (string)$response->getBody();
            $decoded    = json_decode($rawBody, true);

            if ($statusCode >= 200 && $statusCode < 300) {
                if (is_array($decoded) && isset($decoded['type']) && $decoded['type'] === 'Error') {
                    $this->logger->error('Fortis API returned error in 2xx response', [
                        'url'         => $url,
                        'method'      => $method,
                        'status_code' => $statusCode,
                        'error_type'  => $decoded['type'],
                        'detail'      => $decoded['detail'] ?? null,
                    ]);

                    return ApiResponse::error(
                        $statusCode,
                        $decoded['detail'] ?? $decoded['title'] ?? 'API error',
                        $decoded['meta']['errors'] ?? null,
                        $rawBody
                    );
                }

                $this->logger->info('Fortis API request succeeded', [
                    'url'         => $url,
                    'method'      => $method,
                    'status_code' => $statusCode,
                ]);

                return ApiResponse::success($statusCode, $decoded, $rawBody);
            }

            $message = 'HTTP error';
            if (is_array($decoded)) {
                $message = $decoded['detail'] ?? $decoded['title'] ?? $decoded['message'] ?? 'HTTP error';
            }

            $this->logger->error('Fortis API request failed', [
                'url'         => $url,
                'method'      => $method,
                'status_code' => $statusCode,
                'message'     => $message,
            ]);

            return ApiResponse::error($statusCode, $message, null, $rawBody);
        } catch (\Psr\Http\Client\ClientExceptionInterface $e) {
            $this->logger->error('Fortis API HTTP client exception', [
                'url'       => $url,
                'method'    => $method,
                'exception' => get_class($e),
                'error'     => $e->getMessage(),
            ]);

            return ApiResponse::error(0, 'HTTP request failed: ' . $e->getMessage());
        } catch (\Throwable $e) {
            $this->logger->error('Fortis API unexpected exception', [
                'url'       => $url,
                'method'    => $method,
                'exception' => get_class($e),
                'error'     => $e->getMessage(),
                'trace'     => $e->getTraceAsString(),
            ]);

            return ApiResponse::error(0, 'Unexpected error: ' . $e->getMessage());
        }
    }
}
