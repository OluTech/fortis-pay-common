<?php

namespace Fortis\Api\Http;

use Fortis\Api\Contracts\TokenStorageInterface;

/**
 * No-op token storage for platforms that don't need vault/saved cards.
 */
class NullTokenStorage implements TokenStorageInterface
{
    public function getTokenById(string $id): ?string
    {
        return null;
    }

    public function getPaymentTypeById(string $id): ?string
    {
        return null;
    }

    public function vaultCard(string $tokenId, array $cardData, string $customerId): void
    {
    }

    public function deleteToken(string $id): void
    {
    }
}
