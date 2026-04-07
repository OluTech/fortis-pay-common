<?php

namespace Fortis\Api\Contracts;

/**
 * Platform-agnostic token/vault storage interface.
 *
 * Each platform implements this to store and retrieve saved payment method tokens
 * using their native database or storage system.
 */
interface TokenStorageInterface
{
    /**
     * Resolve a platform vault ID to the actual Fortis token string.
     */
    public function getTokenById(string $id): ?string;

    /**
     * Get the payment type (cc, ach, etc.) for a stored token.
     */
    public function getPaymentTypeById(string $id): ?string;

    /**
     * Store a new vaulted card/payment method.
     *
     * @param string $tokenId Fortis token ID
     * @param array $cardData Card data from Fortis (first_six, last_four, payment_method, etc.)
     * @param string $customerId Platform customer ID
     */
    public function vaultCard(string $tokenId, array $cardData, string $customerId): void;

    /**
     * Delete a stored token by platform vault ID.
     */
    public function deleteToken(string $id): void;
}
