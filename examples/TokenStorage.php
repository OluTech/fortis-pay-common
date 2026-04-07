<?php

namespace Fortis\Adapters;

use Fortis\Api\Contracts\TokenStorageInterface;

/**
 * Example TokenStorage Implementation
 *
 * Copy this file to your application and implement the methods to store and retrieve tokens
 * using your platform's storage mechanism (database, cache, file system, etc.).
 *
 * This example uses in-memory array storage for demonstration. In production, you would typically:
 * - Store in database table
 * - Store in Redis/Memcached
 * - Store in platform's native storage system
 * - Encrypt tokens at rest
 *
 * @example
 * $storage = new TokenStorage();
 * $storage->vaultCard('token_12345', ['last_four' => '4242', 'payment_method' => 'cc'], 'customer_1');
 * $token = $storage->getTokenById('vault_xyz');
 */
class TokenStorage implements TokenStorageInterface
{
    /**
     * In-memory storage for demonstration.
     * In production, replace this with database/cache storage.
     *
     * Structure:
     * [
     *     'vault_id' => [
     *         'fortis_token' => 'tok_12345',
     *         'payment_type' => 'cc',
     *         'last_four' => '4242',
     *         'first_six' => '424242',
     *         'customer_id' => 'cust_123',
     *         'created_at' => 1234567890,
     *     ]
     * ]
     */
    private array $storage = [];

    public function __construct(array $initialData = [])
    {
        $this->storage = $initialData;
    }

    public function getTokenById(string $id): ?string
    {
        // TODO: Replace with database query
        // Example: SELECT fortis_token FROM payment_tokens WHERE id = ?
        return $this->storage[$id]['fortis_token'] ?? null;
    }

    public function getPaymentTypeById(string $id): ?string
    {
        // TODO: Replace with database query
        // Example: SELECT payment_type FROM payment_tokens WHERE id = ?
        return $this->storage[$id]['payment_type'] ?? null;
    }

    public function vaultCard(string $tokenId, array $cardData, string $customerId): void
    {
        // Generate unique vault ID for this token
        $vaultId = $this->generateVaultId();

        // TODO: Replace with database insert
        // Example: INSERT INTO payment_tokens (fortis_token, payment_type, last_four, customer_id, created_at) VALUES (?, ?, ?, ?, ?)
        $this->storage[$vaultId] = [
            'fortis_token' => $tokenId,
            'payment_type' => $cardData['payment_method'] ?? 'cc',
            'last_four'    => $cardData['last_four'] ?? null,
            'first_six'    => $cardData['first_six'] ?? null,
            'customer_id'  => $customerId,
            'created_at'   => time(),
        ];
    }

    public function deleteToken(string $id): void
    {
        // TODO: Replace with database delete
        // Example: DELETE FROM payment_tokens WHERE id = ?
        unset($this->storage[$id]);
    }

    /**
     * Generate a unique vault ID for storing the token
     *
     * @return string
     */
    private function generateVaultId(): string
    {
        return 'vault_' . bin2hex(random_bytes(16));
    }

    /**
     * Helper method to add token (for testing/seeding)
     *
     * @param string $id Vault ID
     * @param array $data Token data
     *
     * @return void
     */
    public function addToken(string $id, array $data): void
    {
        $this->storage[$id] = $data;
    }

    /**
     * Helper method to get all tokens (for testing/debugging)
     *
     * @return array
     */
    public function getAllTokens(): array
    {
        return $this->storage;
    }

    /**
     * Helper method to clear all tokens (for testing)
     *
     * @return void
     */
    public function clearAllTokens(): void
    {
        $this->storage = [];
    }
}

