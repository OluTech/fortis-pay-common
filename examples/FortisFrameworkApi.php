<?php

namespace Fortis\FrameworkApi;

use Exception;
use Fortis\Api\Contracts\ConfigProviderInterface;
use Fortis\Api\Contracts\TokenStorageInterface;

/**
 * FortisFrameworkApi
 *
 * This is an example template for adapting the Fortis Common Library to a specific platform.
 *
 * Copy this file to your platform-specific repository and implement the abstract methods
 * to integrate with your platform's configuration system, database, or API.
 *
 * This class serves as both ConfigProvider and demonstrates token storage patterns.
 * For separation of concerns, you may want to split this into separate ConfigProvider
 * and TokenStorage classes in your implementation.
 */
class FortisFrameworkApi implements ConfigProviderInterface
{
    // Developer IDs for authentication with Fortis API
    public const DEVELOPER_ID_SANDBOX    = 'your_sandbox_developer_id';
    public const DEVELOPER_ID_PRODUCTION = 'your_production_developer_id';

    protected string $id;
    protected array $settings;
    protected string $environment;

    /**
     * Initialize the Fortis API adapter with platform-specific settings
     *
     * @param string $id Platform-specific identifier (store ID, merchant ID, etc.)
     * @param array $settings Configuration settings for this instance
     */
    public function __construct(string $id, array $settings)
    {
        $this->id          = $id;
        $this->settings    = $settings;
        $this->environment = $settings['environment'] ?? 'sandbox';
    }

    // ============================================================================
    // ConfigProviderInterface Implementation
    // ============================================================================

    /**
     * Get the merchant user ID from Fortis
     *
     * @return string
     */
    public function getUserId(): string
    {
        return $this->environment !== 'sandbox'
            ? ($this->settings['production_user_id'] ?? '')
            : ($this->settings['sandbox_user_id'] ?? '');
    }

    /**
     * Get the merchant API key from Fortis
     *
     * @return string
     */
    public function getUserApiKey(): string
    {
        return $this->environment !== 'sandbox'
            ? ($this->settings['production_user_api_key'] ?? '')
            : ($this->settings['sandbox_user_api_key'] ?? '');
    }

    /**
     * Get the Fortis location ID
     *
     * @return string
     */
    public function getLocationId(): string
    {
        return $this->environment !== 'sandbox'
            ? ($this->settings['production_location_id'] ?? '')
            : ($this->settings['sandbox_location_id'] ?? '');
    }

    /**
     * Get the product ID for credit card transactions
     *
     * @return string
     */
    public function getProductIdCC(): string
    {
        return $this->environment !== 'sandbox'
            ? ($this->settings['production_product_id_cc'] ?? '')
            : ($this->settings['sandbox_product_id_cc'] ?? '');
    }

    /**
     * Get the product ID for ACH transactions
     *
     * @return string
     */
    public function getProductIdACH(): string
    {
        return $this->environment !== 'sandbox'
            ? ($this->settings['production_product_id_ach'] ?? '')
            : ($this->settings['sandbox_product_id_ach'] ?? '');
    }

    /**
     * Get the current environment (sandbox or production)
     *
     * @return string
     */
    public function getEnvironment(): string
    {
        return $this->environment;
    }

    /**
     * Get the developer ID for the current environment
     *
     * @return string
     */
    public function getDeveloperId(): string
    {
        return $this->environment !== 'sandbox'
            ? self::DEVELOPER_ID_PRODUCTION
            : self::DEVELOPER_ID_SANDBOX;
    }

    /**
     * Get the base API URL for the current environment
     *
     * @param bool $v2 Whether to return the v2 API endpoint
     *
     * @return string
     */
    public function getApiUrl(bool $v2 = false): string
    {
        $baseUrl = $this->environment === 'production'
            ? 'https://api.fortis.tech'
            : 'https://api.sandbox.fortis.tech';

        return $v2 ? $baseUrl . '/v2' : $baseUrl;
    }

    /**
     * Get the transaction action type (sale or auth-only)
     *
     * @return string
     */
    public function getAction(): string
    {
        return ($this->settings['transaction_type'] ?? 'sale') === 'auth-only'
            ? 'auth-only'
            : 'sale';
    }

    /**
     * Check if ACH payments are enabled
     *
     * @return bool
     */
    public function isACHEnabled(): bool
    {
        return ($this->settings['ach_enabled'] ?? $this->settings['ach'] ?? 'N') === 'Y';
    }

    /**
     * Check if credit card payments are enabled
     *
     * @return bool
     */
    public function isCCEnabled(): bool
    {
        return ($this->settings['cc_enabled'] ?? $this->settings['cc'] ?? 'Y') === 'Y';
    }

    /**
     * Check if payment method vaulting is enabled
     *
     * @return bool
     */
    public function isVaultEnabled(): bool
    {
        return ($this->settings['vault_enabled'] ?? $this->settings['tokenization'] ?? 'N') === 'Y';
    }

    /**
     * Check if Level 3 transaction data is enabled
     *
     * @return bool
     */
    public function isLevel3Enabled(): bool
    {
        return ($this->settings['level3_enabled'] ?? $this->settings['level3'] ?? 'N') === 'Y';
    }

    /**
     * Get an arbitrary configuration value
     *
     * @param string $key Configuration key
     * @param mixed $default Default value if key not found
     *
     * @return mixed
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->settings[$key] ?? $default;
    }

    // ============================================================================
    // Token Storage Methods
    // ============================================================================

    /**
     * Retrieve a Fortis token by platform vault ID
     *
     * TODO: Implement this to query your platform's database or storage
     *
     * @param string $id Platform vault ID
     *
     * @return string|null Fortis token string or null if not found
     */
    public function getTokenById(string $id): ?string
    {
        // Example implementation:
        // return $this->database->query('SELECT fortis_token FROM payment_tokens WHERE id = ?', [$id]);
        return null;
    }

    /**
     * Get the payment type (cc, ach, etc.) for a stored token
     *
     * TODO: Implement this to query your platform's database or storage
     *
     * @param string $id Platform vault ID
     *
     * @return string|null Payment type or null if not found
     */
    public function getPaymentTypeById(string $id): ?string
    {
        // Example implementation:
        // return $this->database->query('SELECT payment_type FROM payment_tokens WHERE id = ?', [$id]);
        return null;
    }

    /**
     * Store a new vaulted payment method
     *
     * TODO: Implement this to store the token in your platform's database
     *
     * @param string $tokenId Fortis token ID
     * @param array $cardData Card data from Fortis (payment_method, last_four, first_six, etc.)
     * @param string $customerId Platform customer ID
     *
     * @return void
     */
    public function vaultCard(string $tokenId, array $cardData, string $customerId): void
    {
        // Example implementation:
        // $this->database->insert('payment_tokens', [
        //     'fortis_token'  => $tokenId,
        //     'payment_type'  => $cardData['payment_method'] ?? 'cc',
        //     'last_four'     => $cardData['last_four'] ?? null,
        //     'customer_id'   => $customerId,
        //     'created_at'    => now(),
        // ]);
    }

    /**
     * Delete a stored token
     *
     * TODO: Implement this to remove the token from your platform's database
     *
     * @param string $id Platform vault ID
     *
     * @return void
     */
    public function deleteToken(string $id): void
    {
        // Example implementation:
        // $this->database->delete('payment_tokens', ['id' => $id]);
    }

    // ============================================================================
    // Helper Methods
    // ============================================================================

    /**
     * Log an error message
     *
     * @param string $title Error title/category
     * @param mixed $data Error data or context
     *
     * @return void
     */
    public static function logError(string $title, mixed $data): void
    {
        // TODO: Implement platform-specific error logging
    }

    /**
     * Log an info message
     *
     * @param string $title Info title/category
     * @param mixed $data Info data or context
     *
     * @return void
     */
    public static function logInfo(string $title, mixed $data): void
    {
        // TODO: Implement platform-specific info logging
    }

    /**
     * Get formatted exception details
     *
     * @param Exception $exception Exception to format
     *
     * @return string Formatted exception message
     */
    public static function getExceptionDetails(Exception $exception): string
    {
        return sprintf(
            '%s in %s at line %d',
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine()
        );
    }
}
