<?php

namespace Fortis\FrameworkApi;

use Exception;

class FortisFrameworkApi
{
    public const DEVELOPER_ID_SANDBOX    = '123123';
    public const DEVELOPER_ID_PRODUCTION = '123123';
    public $id;
    public $detail;
    private array $processorParameters;
    private string $mode;

    public function __construct(string $id, array $processorParameters)
    {
        $this->id                  = $id;
        $this->processorParameters = $processorParameters;
        $this->mode                = $processorParameters['mode'];
    }

    /**
     * Get fortis level3 enabled setting
     *
     * @return bool
     */
    public function getLevel3Enabled(): bool
    {
        return $this->processorParameters['level3'] ?? 'N' === 'Y';
    }


    /**
     * Get fortis ach setting for ach enabled
     *
     * @return bool
     */
    public function getACHEnabled(): bool
    {
        return $this->processorParameters['ach'] ?? 'N' === 'Y';
    }

    /**
     * Get fortis setting for cc enabled
     *
     * @return bool
     */
    public function getCCEnabled(): bool
    {
        return $this->processorParameters['cc'] ?? 'N' === 'Y';
    }

    /**
     * Get fortis environment from settings
     *
     * @return string|$environment
     */
    public function getEnvironment(): string
    {
        return $this->mode;
    }

    /**
     * Get fortis vault enabled setting
     *
     * @return bool
     */
    public function vaultEnabled(): bool
    {
        return $this->processorParameters['tokenization'] ?? 'N' === 'Y';
    }

    /**
     * Lookup token in database by ID and return actual token
     *
     * @param $id
     *
     * @return string
     */
    public function getTokenById($id): string
    {
        return '';
    }

    /**
     * @param $token_id
     * @param $saved_account from fortis
     * @param $customer_id
     */
    public function vaultCard($token_id, $saved_account, $customer_id)
    {
    }

    /**
     * Get fortis action sale or authonly from settings
     *
     * @return string $action
     */
    public function getAction(): string
    {
        if ($this->processorParameters['transaction_type'] !== 'auth-only') {
            return 'sale';
        }

        return 'auth-only';
    }

    /**
     * Get User Fortis ID based on the currently configured environment
     *
     * @return string $userId
     */
    public function getUserId(): string
    {
        if ($this->mode !== 'sandbox') {
            return $this->processorParameters['production_user_id'];
        }

        return $this->processorParameters['sandbox_user_id'];
    }

    /**
     * Get Fortis User API Key based on the currently configured environment
     *
     * @return string $userApiKey
     */
    public function getUserApiKey(): string
    {
        if ($this->mode !== 'sandbox') {
            return $this->processorParameters['production_user_api_key'];
        }

        return $this->processorParameters['sandbox_user_api_key'];
    }

    /**
     * Get Fortis User Product ID based on the currently configured environmet
     *
     * @return string $locationId
     */
    public function getLocationId(): string
    {
        if ($this->mode !== 'sandbox') {
            return $this->processorParameters['production_location_id'];
        }

        return $this->processorParameters['sandbox_location_id'];
    }

    public static function logError($title, $data)
    {
    }

    public static function logInfo($title, $data)
    {
    }

    public static function getExceptionDetails(Exception $exception): string
    {
        return $exception->getMessage() + ' in ' + $exception->getFile() + ' at line ' + $exception->getLine();
    }

    /**
     * @return string
     */
    public function getProductIdCC(): string
    {
        if ($this->mode !== 'sandbox') {
            return $this->processorParameters['production_product_id_cc'];
        }

        return $this->processorParameters['sandbox_product_id_cc'];
    }

    /**
     * @return string
     */
    public function getProductIdACH(): string
    {
        if ($this->mode !== 'sandbox') {
            return $this->processorParameters['production_product_id_ach'];
        }

        return $this->processorParameters['sandbox_product_id_ach'];
    }
}
