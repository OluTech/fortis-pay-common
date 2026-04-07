<?php

namespace Fortis\Api\Http;

use Psr\Log\LoggerInterface;

/**
 * Retry handler with exponential backoff for transient API failures.
 */
class RetryHandler
{
    private int $maxAttempts;
    private int $baseDelayMs;
    private float $multiplier;
    private array $retryableStatusCodes;

    public function __construct(
        int $maxAttempts = 3,
        int $baseDelayMs = 200,
        float $multiplier = 2.0,
        array $retryableStatusCodes = [408, 429, 500, 502, 503, 504]
    ) {
        $this->maxAttempts          = max(1, $maxAttempts);
        $this->baseDelayMs          = max(0, $baseDelayMs);
        $this->multiplier           = max(1.0, $multiplier);
        $this->retryableStatusCodes = $retryableStatusCodes;
    }

    /**
     * Execute an operation with retry logic.
     *
     * @param callable $operation Returns an ApiResponse
     * @param LoggerInterface|null $logger
     *
     * @return ApiResponse
     */
    public function execute(callable $operation, ?LoggerInterface $logger = null): ApiResponse
    {
        $lastResponse = null;

        for ($attempt = 1; $attempt <= $this->maxAttempts; $attempt++) {
            try {
                $result = $operation();

                if ($result instanceof ApiResponse) {
                    if (!$result->success && in_array($result->statusCode, $this->retryableStatusCodes, true)) {
                        $lastResponse = $result;
                        if ($attempt < $this->maxAttempts) {
                            $delay = $this->calculateDelay($attempt);
                            $logger?->warning('Retryable API error, attempt {attempt}/{max}', [
                                'attempt'    => $attempt,
                                'max'        => $this->maxAttempts,
                                'statusCode' => $result->statusCode,
                                'delay_ms'   => $delay,
                            ]);
                            usleep($delay * 1000);
                            continue;
                        }
                    }

                    return $result;
                }

                return ApiResponse::error(0, 'Operation did not return an ApiResponse');
            } catch (\Throwable $e) {
                $lastResponse = ApiResponse::error(0, $e->getMessage());

                if ($attempt < $this->maxAttempts) {
                    $delay = $this->calculateDelay($attempt);
                    $logger?->warning('API call exception, retrying attempt {attempt}/{max}', [
                        'attempt'  => $attempt,
                        'max'      => $this->maxAttempts,
                        'error'    => $e->getMessage(),
                        'delay_ms' => $delay,
                    ]);
                    usleep($delay * 1000);
                }
            }
        }

        return $lastResponse ?? ApiResponse::error(0, 'All retry attempts exhausted');
    }

    private function calculateDelay(int $attempt): int
    {
        return (int)($this->baseDelayMs * pow($this->multiplier, $attempt - 1));
    }
}
