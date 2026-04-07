<?php

namespace Fortis\Adapters;

use Fortis\Api\Contracts\ConfigProviderInterface;

/**
 * Example ConfigProvider Implementation
 *
 * Copy this file to your application and implement the methods to load configuration
 * from your platform's configuration system (database, environment variables, config files, etc.).
 *
 * This example shows a simple array-based configuration. In production, you would typically:
 * - Load from database
 * - Load from environment variables
 * - Load from framework configuration system
 * - Load from API
 *
 * @example
 * $config = [
 *     'environment' => 'sandbox',
 *     'sandbox_user_id' => 'user_123',
 *     'sandbox_api_key' => 'key_abc',
 *     // ... more settings
 * ];
 * $provider = new ConfigProvider($config);
 */
class ConfigProvider implements ConfigProviderInterface
{
    private array $config;

    /**
     * Initialize with configuration array
     *
     * @param array $config Configuration settings with keys:
     *   - environment: 'sandbox' or 'production'
     *   - sandbox_user_id: Sandbox Fortis user ID
     *   - sandbox_api_key: Sandbox Fortis API key
     *   - sandbox_location_id: Sandbox Fortis location ID
     *   - sandbox_product_id_cc: Sandbox product ID for credit cards
     *   - sandbox_product_id_ach: Sandbox product ID for ACH
     *   - production_user_id: Production Fortis user ID
     *   - production_api_key: Production Fortis API key
     *   - production_location_id: Production Fortis location ID
     *   - production_product_id_cc: Production product ID for credit cards
     *   - production_product_id_ach: Production product ID for ACH
     *   - developer_id: Fortis developer ID
     *   - action: 'sale' or 'auth-only' (default: 'sale')
     *   - ach_enabled: boolean (default: false)
     *   - cc_enabled: boolean (default: true)
     *   - vault_enabled: boolean (default: false)
     *   - level3_enabled: boolean (default: false)
     */
    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function getUserId(): string
    {
        $env = $this->getEnvironment();

        return $this->config["{$env}_user_id"] ?? '';
    }

    public function getUserApiKey(): string
    {
        $env = $this->getEnvironment();

        return $this->config["{$env}_api_key"] ?? '';
    }

    public function getLocationId(): string
    {
        $env = $this->getEnvironment();

        return $this->config["{$env}_location_id"] ?? '';
    }

    public function getProductIdCC(): string
    {
        $env = $this->getEnvironment();

        return $this->config["{$env}_product_id_cc"] ?? '';
    }

    public function getProductIdACH(): string
    {
        $env = $this->getEnvironment();

        return $this->config["{$env}_product_id_ach"] ?? '';
    }

    public function getEnvironment(): string
    {
        return $this->config['environment'] ?? 'sandbox';
    }

    public function getDeveloperId(): string
    {
        return $this->config['developer_id'] ?? '';
    }

    public function getApiUrl(bool $v2 = false): string
    {
        $baseUrl = $this->getEnvironment() === 'production'
            ? 'https://api.fortis.tech'
            : 'https://api.sandbox.fortis.tech';

        return $v2 ? $baseUrl . '/v2' : $baseUrl;
    }

    public function getAction(): string
    {
        return $this->config['action'] ?? 'sale';
    }

    public function isACHEnabled(): bool
    {
        return (bool)($this->config['ach_enabled'] ?? false);
    }

    public function isCCEnabled(): bool
    {
        return (bool)($this->config['cc_enabled'] ?? true);
    }

    public function isVaultEnabled(): bool
    {
        return (bool)($this->config['vault_enabled'] ?? false);
    }

    public function isLevel3Enabled(): bool
    {
        return (bool)($this->config['level3_enabled'] ?? false);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->config[$key] ?? $default;
    }
}

