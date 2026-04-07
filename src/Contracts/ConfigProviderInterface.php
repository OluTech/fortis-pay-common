<?php

namespace Fortis\Api\Contracts;

/**
 * Platform-agnostic configuration provider for Fortis API.
 *
 * Platform-specific implementations provide Fortis credentials and settings
 * from their native configuration system (database, configuration files, etc.).
 */
interface ConfigProviderInterface
{
    public function getUserId(): string;

    public function getUserApiKey(): string;

    public function getLocationId(): string;

    public function getProductIdCC(): string;

    public function getProductIdACH(): string;

    /**
     * @return string 'sandbox' or 'production'
     */
    public function getEnvironment(): string;

    public function getDeveloperId(): string;

    /**
     * Get the base API URL for the current environment.
     *
     * @param bool $v2 Whether to return the v2 API URL
     *
     * @return string Full base URL (e.g., 'https://api.fortis.tech')
     */
    public function getApiUrl(bool $v2 = false): string;

    /**
     * @return string 'sale' or 'auth-only'
     */
    public function getAction(): string;

    public function isACHEnabled(): bool;

    public function isCCEnabled(): bool;

    public function isVaultEnabled(): bool;

    public function isLevel3Enabled(): bool;

    /**
     * Get an arbitrary configuration value by key.
     */
    public function get(string $key, mixed $default = null): mixed;
}
