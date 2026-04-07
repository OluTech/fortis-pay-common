<?php

namespace Fortis\Api\Http;

/**
 * Standardized API response DTO for all Fortis API calls.
 *
 * Provides a consistent response structure across all platforms,
 * replacing ad-hoc string/array returns with a typed object.
 */
class ApiResponse
{
    public function __construct(
        public readonly bool $success,
        public readonly int $statusCode,
        public readonly ?array $data = null,
        public readonly ?string $message = null,
        public readonly ?array $errors = null,
        public readonly ?string $rawBody = null,
    ) {
    }

    public static function success(int $statusCode, ?array $data = null, string $rawBody = ''): self
    {
        return new self(
            success:    true,
            statusCode: $statusCode,
            data:       $data,
            rawBody:    $rawBody,
        );
    }

    public static function error(
        int $statusCode,
        string $message,
        ?array $errors = null,
        string $rawBody = ''
    ): self {
        return new self(
            success:    false,
            statusCode: $statusCode,
            message:    $message,
            errors:     $errors,
            rawBody:    $rawBody,
        );
    }

    /**
     * Get nested data value using dot notation (e.g. 'data.client_token').
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $data = $this->data;
        if ($data === null) {
            return $default;
        }

        foreach (explode('.', $key) as $segment) {
            if (!is_array($data) || !array_key_exists($segment, $data)) {
                return $default;
            }
            $data = $data[$segment];
        }

        return $data;
    }

    public function toArray(): array
    {
        return [
            'success'    => $this->success,
            'statusCode' => $this->statusCode,
            'data'       => $this->data,
            'message'    => $this->message,
            'errors'     => $this->errors,
        ];
    }
}
