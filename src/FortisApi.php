<?php

namespace Fortis\Api;

use Fortis\Api\Contracts\ConfigProviderInterface;
use Fortis\Api\Contracts\TokenStorageInterface;
use Fortis\Api\Http\ApiResponse;
use Fortis\Api\Http\FortisHttpClient;
use Fortis\Api\Http\NullTokenStorage;
use Psr\Log\LoggerInterface;

/**
 * Platform-agnostic Fortis Payment API client.
 *
 * This class contains NO platform-specific dependencies. All platform concerns
 * (configuration, token storage, HTTP transport, logging) are injected via
 * PSR-compliant interfaces.
 *
 * Platform-specific implementations provide their own implementations of
 * ConfigProviderInterface and TokenStorageInterface.
 */
class FortisApi
{
    // ── API Base URLs ──────────────────────────────────────────────────
    public const FORTIS_URL_SANDBOX    = 'https://api.sandbox.fortis.tech';
    public const FORTIS_URL_PRODUCTION = 'https://api.fortis.tech';

    public const FORTIS_V2_URL_SANDBOX    = 'https://api.sandbox.zeamster.com';
    public const FORTIS_V2_URL_PRODUCTION = 'https://api.zeamster.com';

    // ── Configuration Key Constants ────────────────────────────────────
    public const VAULT                         = 'vault';
    public const TOKENIZATION                  = 'Tokenization';
    public const ACH                           = 'ach';
    public const CC                            = 'cc';
    public const LEVEL3                        = 'level3';
    public const APPLEPAY                      = 'applepay';
    public const GOOGLEPAY                     = 'googlepay';
    public const ENVIRONMENT                   = 'environment';
    public const PRODUCTION_HOST_DOMAIN        = 'production_host_domain';
    public const PRODUCTION_USER_ID            = 'production_user_id';
    public const PRODUCTION_USER_API_KEY       = 'production_user_api_key';
    public const PRODUCTION_PRODUCT_ID_CC      = 'production_product_id_cc';
    public const PRODUCTION_PRODUCT_ID_ACH     = 'production_product_id_ach';
    public const PRODUCTION_LOCATION_ID        = 'production_location_id';
    public const SANDBOX_HOST_DOMAIN           = 'sandbox_host_domain';
    public const SANDBOX_USER_ID               = 'sandbox_user_id';
    public const SANDBOX_USER_API_KEY          = 'sandbox_user_api_key';
    public const SANDBOX_PRODUCT_ID_CC         = 'sandbox_product_id_cc';
    public const SANDBOX_PRODUCT_ID_ACH        = 'sandbox_product_id_ach';
    public const SANDBOX_LOCATION_ID           = 'sandbox_location_id';
    public const TRANSACTION_TYPE              = 'transaction_type';
    public const ACTION                        = 'action';
    public const THEME                         = 'theme';
    public const FLOATINGLABELS                = 'floatingLabels';
    public const SHOWVALIDATIONANIMATION       = 'showValidationAnimation';
    public const COLORBUTTONSELECTEDTEXT       = 'colorButtonSelectedText';
    public const COLORBUTTONSELECTEDBACKGROUND = 'colorButtonSelectedBackground';
    public const COLORBUTTONACTIONBACKGROUND   = 'colorButtonActionBackground';
    public const COLORBUTTONACTIONTEXT         = 'colorButtonActionText';
    public const COLORBUTTONBACKGROUND         = 'colorButtonBackground';
    public const COLORBUTTONTEXT               = 'colorButtonText';
    public const COLORFIELDBACKGROUND          = 'colorFieldBackground';
    public const COLORFIELDBORDER              = 'colorFieldBorder';
    public const COLORTEXT                     = 'colorText';
    public const COLORLINK                     = 'colorLink';
    public const FONTSIZE                      = 'fontSize';
    public const MARGINSPACING                 = 'marginSpacing';
    public const BORDERRADIUS                  = 'borderRadius';
    public const ADDITIONALTEXT                = 'additionalText';
    public const DEFAULT_COMMODITY_CODE        = 'default_commodity_code';
    public const DEFAULT_PRODUCT_CODE          = 'default_product_code';
    public const CENTS_MULTIPLIER              = '100';

    public const WHITE        = '#ffffff';
    public const BLACK        = '#000000';
    public const COLOURBUTTON = '#0700ff';
    public const COLOURLINK   = '#0000ff';

    // ── Runtime State ──────────────────────────────────────────────────
    public string $user_id;
    public string $user_api_key;
    public string $location_id;
    public ?string $product_id_cc = null;
    public ?string $product_id_ach = null;
    public string $action;
    public $result;
    public ?string $transactionType = null;
    public ?string $paymentMethod = null;
    public $transaction;

    protected ConfigProviderInterface $config;
    protected TokenStorageInterface $tokenStorage;
    protected FortisHttpClient $httpClient;
    protected LoggerInterface $logger;
    protected string $developerId;

    public function __construct(
        ConfigProviderInterface $config,
        FortisHttpClient $httpClient,
        LoggerInterface $logger,
        ?TokenStorageInterface $tokenStorage = null,
    ) {
        $this->config       = $config;
        $this->httpClient   = $httpClient;
        $this->logger       = $logger;
        $this->tokenStorage = $tokenStorage ?? new NullTokenStorage();

        $this->user_id      = $config->getUserId();
        $this->user_api_key = $config->getUserApiKey();
        $this->location_id  = $config->getLocationId();
        $this->action       = $config->getAction();
        $this->developerId  = $config->getDeveloperId();

        if ($config->isCCEnabled()) {
            $this->product_id_cc = $config->getProductIdCC();
        }
        if ($config->isACHEnabled()) {
            $this->product_id_ach = $config->getProductIdACH();
        }
    }

    // ═══════════════════════════════════════════════════════════════════
    //  Level 3 Processing
    // ═══════════════════════════════════════════════════════════════════

    /**
     * Add Level 3 data to a transaction.
     *
     * @return array{success: bool, message: string, data: mixed}
     */
    public function addLevel3(array $lineItems, array $level3Data = []): array
    {
        $transactionId = $this->transaction->id;
        $cardType      = strtolower($this->transaction->account_type ?? 'unknown');

        $result = ['success' => false, 'message' => '', 'data' => null];

        $this->logger->info('Processing Level 3 for card type', [
            'transaction_id'     => $transactionId,
            'detected_card_type' => $cardType,
            'account_type'       => $this->transaction->account_type ?? 'unknown',
            'payment_method'     => $this->transaction->payment_method ?? 'unknown',
        ]);

        try {
            if (in_array($cardType, ['visa'])) {
                $result = $this->sendVisaLevel3($transactionId, $lineItems, $level3Data);
            } elseif (in_array($cardType, ['mastercard', 'mc'])) {
                $result = $this->sendMasterCardLevel3($transactionId, $lineItems, $level3Data);
            } else {
                $result['message'] = "Level 3 data not supported for card type: {$cardType}";
                $this->logger->info('Level 3 skipped - unsupported card type', [
                    'transaction_id' => $transactionId,
                    'card_type'      => $cardType,
                ]);
            }
        } catch (\Throwable $e) {
            $result['message'] = 'Level 3 processing failed: ' . $e->getMessage();
            $this->logger->error('Level 3 processing error', [
                'transaction_id' => $transactionId,
                'card_type'      => $cardType,
                'exception_class' => get_class($e),
                'error'          => $e->getMessage(),
                'trace'          => $e->getTraceAsString(),
            ]);
        }

        return $result;
    }

    protected function sendVisaLevel3(string $transactionId, array $lineItems, array $level3Data): array
    {
        $level3PayloadData = [];

        if (!empty($level3Data)) {
            foreach ($level3Data as $key => $value) {
                if ($value !== null && $value !== '') {
                    if (in_array($key, ['sales_tax', 'tax_amount', 'duty_amount', 'freight_amount', 'national_tax'])) {
                        $level3PayloadData[$key] = (int)bcmul((string)$value, self::CENTS_MULTIPLIER, 0);
                    } else {
                        $level3PayloadData[$key] = $value;
                    }
                }
            }
        }

        $level3PayloadData['line_items'] = [];
        foreach ($lineItems as $lineItem) {
            $item = [];
            if (isset($lineItem['description'])) {
                $item['description'] = (string)$lineItem['description'];
            }
            if (isset($lineItem['commodity_code'])) {
                $item['commodity_code'] = (string)$lineItem['commodity_code'];
            }
            if (isset($lineItem['product_code'])) {
                $item['product_code'] = (string)$lineItem['product_code'];
            }
            if (isset($lineItem['unit_code'])) {
                $item['unit_code'] = (string)$lineItem['unit_code'];
            }
            if (isset($lineItem['unit_cost'])) {
                $item['unit_cost'] = (int)bcmul((string)$lineItem['unit_cost'], self::CENTS_MULTIPLIER, 0);
            }
            if (isset($lineItem['quantity']) && $lineItem['quantity'] > 0) {
                $item['quantity'] = (int)$lineItem['quantity'];
            }
            if (isset($lineItem['discount_amount']) && $lineItem['discount_amount'] > 0) {
                $item['discount_amount'] = (int)bcmul(
                    (string)$lineItem['discount_amount'],
                    self::CENTS_MULTIPLIER,
                    0
                );
            }
            if (isset($lineItem['tax_amount'])) {
                $item['tax_amount'] = (int)bcmul(
                    (string)$lineItem['tax_amount'],
                    self::CENTS_MULTIPLIER,
                    0
                );
            }
            $level3PayloadData['line_items'][] = $item;
        }

        $this->validateLevel3Data($level3PayloadData, $level3PayloadData['line_items'], 'visa');

        $intentData = ['level3_data' => $level3PayloadData];
        $endpoints  = ["/v1/transactions/{$transactionId}/level3/visa"];
        $response   = $this->postLevel3($intentData, $endpoints, 'visa');

        return $this->handleLevel3Response($response, 'Visa', $transactionId, $intentData);
    }

    protected function sendMasterCardLevel3(string $transactionId, array $lineItems, array $level3Data): array
    {
        $level3PayloadData = [];
        $fields            = [
            'destination_country_code',
            'duty_amount',
            'freight_amount',
            'national_tax',
            'sales_tax',
            'shipfrom_zip_code',
            'shipto_zip_code',
            'tax_amount',
            'tax_exempt',
        ];
        foreach ($fields as $field) {
            if (isset($level3Data[$field]) && $level3Data[$field] !== '') {
                switch ($field) {
                    case 'duty_amount':
                    case 'freight_amount':
                    case 'national_tax':
                    case 'sales_tax':
                    case 'tax_amount':
                        $level3PayloadData[$field] = (int)bcmul(
                            (string)$level3Data[$field],
                            self::CENTS_MULTIPLIER,
                            0
                        );
                        break;
                    case 'destination_country_code':
                    case 'shipfrom_zip_code':
                    case 'shipto_zip_code':
                    case 'tax_exempt':
                        $level3PayloadData[$field] = substr((string)$level3Data[$field], 0, 10);
                        break;
                    default:
                        $level3PayloadData[$field] = $level3Data[$field];
                }
            }
        }

        $level3PayloadData['line_items'] = [];
        foreach ($lineItems as $lineItem) {
            $item = [];
            if (isset($lineItem['description'])) {
                $item['description'] = (string)$lineItem['description'];
            }
            if (isset($lineItem['product_code'])) {
                $item['product_code'] = (string)$lineItem['product_code'];
            }
            if (isset($lineItem['unit_code'])) {
                $item['unit_code'] = (string)$lineItem['unit_code'];
            }
            if (isset($lineItem['unit_cost'])) {
                $item['unit_cost'] = (int)bcmul((string)$lineItem['unit_cost'], self::CENTS_MULTIPLIER, 0);
            }
            if (isset($lineItem['quantity']) && $lineItem['quantity'] > 0) {
                $item['quantity'] = (int)$lineItem['quantity'];
            }
            if (isset($lineItem['discount_amount']) && $lineItem['discount_amount'] > 0) {
                $item['discount_amount'] = (int)bcmul(
                    (string)$lineItem['discount_amount'],
                    self::CENTS_MULTIPLIER,
                    0
                );
            }
            if (isset($lineItem['tax_amount'])) {
                $item['tax_amount'] = (int)bcmul(
                    (string)$lineItem['tax_amount'],
                    self::CENTS_MULTIPLIER,
                    0
                );
            }
            if (isset($lineItem['tax_type_id'])) {
                $item['tax_type_id'] = (string)substr($lineItem['tax_type_id'], 0, 2);
            }
            if (isset($lineItem['tax_type_applied'])) {
                $item['tax_type_applied'] = (string)substr($lineItem['tax_type_applied'], 0, 4);
            }
            $item['debit_credit']              = $this->calculateDebitCredit($lineItem);
            $level3PayloadData['line_items'][] = $item;
        }

        $validation = $this->validateLevel3Data($level3PayloadData, $level3PayloadData['line_items'], 'mastercard');
        if (!$validation['valid']) {
            $this->logger->warning('MasterCard Level 3 validation failed', [
                'transaction_id' => $transactionId,
                'issues'         => $validation['issues'],
            ]);
        }

        $intentData = ['level3_data' => $level3PayloadData];
        $endpoints  = ["/v1/transactions/{$transactionId}/level3/master-card"];
        $response   = $this->postLevel3($intentData, $endpoints, 'mastercard');

        return $this->handleLevel3Response($response, 'MasterCard', $transactionId, $intentData);
    }

    public function calculateDebitCredit(array $lineItem): string
    {
        $unitCost       = floatval($lineItem['unit_cost'] ?? 0);
        $quantity       = intval($lineItem['quantity'] ?? 1);
        $discountAmount = floatval($lineItem['discount_amount'] ?? 0);

        return (($unitCost * $quantity) - $discountAmount) < 0 ? 'C' : 'D';
    }

    public function validateLevel3Data($level3Data, $lineItems, $cardType = 'visa'): array
    {
        $issues = [];
        $valid  = true;

        if ($cardType === 'visa') {
            $salesTax    = $level3Data['sales_tax'] ?? 0;
            $nationalTax = $level3Data['national_tax'] ?? 0;
            $taxExempt   = $level3Data['tax_exempt'] ?? '0';

            if ($salesTax == 0 && $nationalTax == 0 && $taxExempt == '0') {
                $issues[] = 'Order appears to have no tax data but is not marked as tax-exempt';
                $valid    = false;
            }

            $lineItemsWithTax = 0;
            $totalLineItemTax = 0;

            foreach ($lineItems as $item) {
                if (isset($item['tax_amount']) && $item['tax_amount'] > 0) {
                    $lineItemsWithTax++;
                    $totalLineItemTax += $item['tax_amount'];
                } elseif (!isset($item['tax_amount'])) {
                    $issues[] = 'Line item missing tax_amount field: ' . ($item['description'] ?? 'Unknown item');
                    $valid    = false;
                }
            }

            if ($taxExempt == '0' && $lineItemsWithTax == 0) {
                $issues[] = 'Non-exempt order has no line items with tax amounts';
                $valid    = false;
            }

            $orderTotalTax = (int)bcmul((string)($salesTax + $nationalTax), self::CENTS_MULTIPLIER, 0);
            $lineTaxDiff   = abs($totalLineItemTax - $orderTotalTax);

            if ($lineTaxDiff > count($lineItems) && $orderTotalTax > 0) {
                $issues[] = sprintf(
                    'Line item tax total (%s cents) does not match order tax total (%s cents)',
                    $totalLineItemTax,
                    $orderTotalTax
                );
            }

            foreach ($lineItems as $item) {
                if (
                    isset($item['commodity_code']) &&
                    in_array($item['commodity_code'], ['0000', 'test1', 'COMMODITY'])
                ) {
                    $issues[] = 'Line item has default commodity code: ' . $item['commodity_code'];
                }
                if (isset($item['product_code']) && in_array($item['product_code'], ['0000', 'PRODUCT'])) {
                    $issues[] = 'Line item has default product code: ' . $item['product_code'];
                }
            }
        }

        if ($cardType === 'mastercard') {
            foreach ($lineItems as $item) {
                if (!isset($item['debit_credit'])) {
                    $issues[] = 'Line item missing required debit_credit field: '
                                . ($item['description'] ?? 'Unknown item');
                    $valid    = false;
                } elseif (!in_array($item['debit_credit'], ['D', 'C'])) {
                    $issues[] = 'Line item has invalid debit_credit value: ' . $item['debit_credit']
                                . ' (must be "D" or "C")';
                    $valid    = false;
                }
            }
        }

        return ['valid' => $valid, 'issues' => $issues];
    }

    protected function handleLevel3Response(
        string $response,
        string $cardType,
        string $transactionId,
        array $sentData
    ): array {
        $result = ['success' => false, 'message' => '', 'data' => null];

        try {
            $decodedResponse = json_decode($response, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('Invalid JSON response: ' . json_last_error_msg());
            }

            if (isset($decodedResponse['type']) && $decodedResponse['type'] === 'Error') {
                $errorMessage = $decodedResponse['detail'] ?? 'Unknown error occurred';

                if (
                    isset($decodedResponse['meta']['errors']['transaction_id']) &&
                    in_array('not qualified for level 3', $decodedResponse['meta']['errors']['transaction_id'])
                ) {
                    $this->logger->warning('Transaction rejected by Fortis: Not qualified for Level 3', [
                        'transaction_id' => $transactionId,
                        'card_type'      => $cardType,
                    ]);

                    $result['message'] = 'Transaction not qualified for Level 3 processing (Fortis business rules)';

                    return $result;
                }

                throw new \Exception($errorMessage);
            }

            if (
                isset($decodedResponse['data']) ||
                (isset($decodedResponse['type']) && $decodedResponse['type'] !== 'Error')
            ) {
                $result['success'] = true;
                $result['message'] = "{$cardType} Level 3 data sent successfully";
                $result['data']    = $decodedResponse;

                $this->logger->info("{$cardType} Level 3 data sent successfully", [
                    'transaction_id'   => $transactionId,
                    'line_items_count' => count($sentData['level3_data']['line_items'] ?? []),
                    'response'         => $decodedResponse,
                ]);
            } else {
                throw new \Exception('Unexpected response format');
            }
        } catch (\Throwable $e) {
            $result['message'] = "{$cardType} Level 3 failed: " . $e->getMessage();

            $this->logger->error("{$cardType} Level 3 processing failed", [
                'transaction_id' => $transactionId,
                'exception_class' => get_class($e),
                'error'          => $e->getMessage(),
                'sent_data'      => $sentData,
                'response'       => $response,
                'trace'          => $e->getTraceAsString(),
            ]);
        }

        return $result;
    }

    // ═══════════════════════════════════════════════════════════════════
    //  Client Tokens
    // ═══════════════════════════════════════════════════════════════════

    /**
     * Get a client token for the payment form.
     *
     * @return mixed String token on success, array with 'error' key on failure
     */
    public function getClientToken($total, $tax_amount, $saveAccount, $useAccount, $method = null): mixed
    {
        $intentData = [
            'action'       => $this->action,
            'amount'       => (int)$total,
            'location_id'  => $this->location_id,
            'save_account' => $saveAccount,
            'message'      => ' ',
        ];

        $intentData['tax_amount'] = max((int)$tax_amount, 1);
        $intentData['methods']    = [];

        if ($method === 'cc') {
            if ($this->config->isCCEnabled() && $this->product_id_cc) {
                $intentData['methods'][] = [
                    'type'                   => 'cc',
                    'product_transaction_id' => $this->product_id_cc,
                ];
            }
        } elseif ($method === 'ach') {
            if ($this->config->isACHEnabled() && isset($this->product_id_ach)) {
                $intentData['methods'][] = [
                    'type'                   => 'ach',
                    'product_transaction_id' => $this->product_id_ach,
                ];
            }
        } else {
            if ($this->config->isCCEnabled() && $this->product_id_cc) {
                $intentData['methods'][] = [
                    'type'                   => 'cc',
                    'product_transaction_id' => $this->product_id_cc,
                ];
            }
            if ($this->config->isACHEnabled() && isset($this->product_id_ach)) {
                $intentData['methods'][] = [
                    'type'                   => 'ach',
                    'product_transaction_id' => $this->product_id_ach,
                ];
            }
        }

        if (empty($intentData['methods'])) {
            return ['error' => 'No payment methods are enabled or configured.'];
        }

        $response = json_decode($this->post($intentData, '/v1/elements/transaction/intention'));

        if (!isset($response->data->client_token)) {
            if (isset($response?->type) && $response->type === 'Error') {
                return ['error' => $response?->detail];
            }

            return ['error' => 'An unknown error occurred'];
        }

        return $response->data->client_token;
    }

    public function getPaymentMethodToken(): string
    {
        $intentData = [
            'action'      => 'tokenization',
            'location_id' => $this->location_id,
        ];

        $intentData['methods'] = [];

        if ($this->product_id_cc) {
            $intentData['methods'][] = [
                'type'                   => 'cc',
                'product_transaction_id' => $this->product_id_cc,
            ];
        }
        if (isset($this->product_id_ach)) {
            $intentData['methods'][] = [
                'type'                   => 'ach',
                'product_transaction_id' => $this->product_id_ach,
            ];
        }

        if (empty($intentData['methods'])) {
            return '';
        }

        $response = json_decode($this->post($intentData, '/v1/elements/transaction/intention'));

        return $response->data->client_token ?? '';
    }

    // ═══════════════════════════════════════════════════════════════════
    //  Transaction Operations
    // ═══════════════════════════════════════════════════════════════════

    public function getTransaction(string $transactionId): string
    {
        return $this->get([], "/v1/transactions/$transactionId?expand[0]=transaction_references");
    }

    public function getTransactionWithHistories(string $transactionId): string
    {
        return $this->get([], "/v1/transactions/$transactionId?expand=transaction_histories,transaction_references");
    }

    public function findOriginalTransactionId(string $refundTransactionId): ?string
    {
        try {
            $response = $this->getTransactionWithHistories($refundTransactionId);
            $data     = json_decode($response, true);

            if (!$data || !isset($data['data'])) {
                return null;
            }

            $txnData = $data['data'];

            if (!empty($txnData['previous_transaction_id'])) {
                return $txnData['previous_transaction_id'];
            }
            if (!empty($txnData['previous_transaction']['id'])) {
                return $txnData['previous_transaction']['id'];
            }

            $refs = $txnData['transaction_references'] ?? [];
            if (!empty($refs) && is_array($refs)) {
                foreach ($refs as $reference) {
                    if (isset($reference['previous_transaction_id'])) {
                        return $reference['previous_transaction_id'];
                    }
                    if (isset($reference['referenced_transaction_id'])) {
                        return $reference['referenced_transaction_id'];
                    }
                }
            }

            $histories = $txnData['transaction_histories'] ?? [];
            if (!empty($histories) && is_array($histories)) {
                foreach ($histories as $history) {
                    if (isset($history['transaction_id']) && $history['transaction_id'] !== $refundTransactionId) {
                        return $history['transaction_id'];
                    }
                    if (isset($history['previous_transaction_id'])) {
                        return $history['previous_transaction_id'];
                    }
                }
            }

            return null;
        } catch (\Throwable $e) {
            $this->logger->error('Error finding original transaction ID', [
                'refund_transaction_id' => $refundTransactionId,
                'exception_class' => get_class($e),
                'error'                 => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return null;
        }
    }

    public function getTransactionType($transaction): ?string
    {
        $this->transaction = $transaction;
        $this->action      = isset($this->transaction->{'@action'})
            ? $this->transaction->{'@action'}
            : $this->config->getAction();

        return isset($this->transaction->type) ? $this->transaction->type : $this->action;
    }

    public function patchTransactionDescription(string $transactionId, string $newDescription): string
    {
        if (empty($transactionId)) {
            throw new \InvalidArgumentException('Transaction ID cannot be null or empty.');
        }

        return $this->patch(['description' => $newDescription], "/v1/transactions/$transactionId");
    }

    /**
     * Process a tokenised (saved card) transaction.
     *
     * @return int Status code (1 = approved, 2 = pending, 3 = error)
     */
    public function doTokenisedTransaction($transaction_amount, $tax_amount, $token_id, $surchargeInfo = null): int
    {
        $token = $this->tokenStorage->getTokenById($token_id);
        if ($token === null) {
            $this->logger->error('doTokenisedTransaction: Token not found for vault ID: ' . $token_id);

            return 3;
        }

        $this->paymentMethod = $this->tokenStorage->getPaymentTypeById($token_id);
        $this->action        = $this->config->getAction();

        $productTransactionID = $this->paymentMethod === 'ach' ? $this->product_id_ach : $this->product_id_cc;
        $subtotal_amount      = $transaction_amount - $tax_amount;

        $intentData = [
            'transaction_amount'     => $transaction_amount,
            'token_id'               => $token,
            'product_transaction_id' => $productTransactionID,
            'tax'                    => $tax_amount,
            'subtotal_amount'        => $subtotal_amount,
        ];

        if ($surchargeInfo) {
            $intentData['subtotal_amount']    = $surchargeInfo['subtotal'];
            $intentData['surcharge_amount']   = $surchargeInfo['surchargeAmount'];
            $intentData['transaction_amount'] = $surchargeInfo['totalAmount'];
        }

        if ($this->action === 'sale') {
            $this->transactionType = 'sale';
            if ($this->paymentMethod === 'ach') {
                $transactionResult   = $this->post($intentData, '/v1/transactions/ach/debit/token');
                $this->paymentMethod = 'ach';
            } else {
                $transactionResult   = $this->post($intentData, '/v1/transactions/cc/sale/token');
                $this->paymentMethod = 'token';
            }
        } else {
            $this->transactionType = 'auth-only';
            if ($this->paymentMethod === 'ach') {
                $transactionResult   = $this->post($intentData, '/v1/transactions/ach/auth-only/token');
                $this->paymentMethod = 'ach';
            } else {
                $transactionResult   = $this->post($intentData, '/v1/transactions/cc/auth-only/token');
                $this->paymentMethod = 'token';
            }
        }

        $this->result      = json_decode(stripslashes($transactionResult));
        $this->transaction = isset($this->result->data) ? $this->result->data : null;

        if ($this->transaction && !isset($this->transaction->type)) {
            $this->transaction->type = $this->transactionType;
        }

        if (isset($this->transaction) && $this->transaction->id) {
            return $this->checkStatus($this->transaction->status_code);
        }

        return 3;
    }

    /**
     * Refund a credit card transaction.
     *
     * @return int|string Status code
     */
    public function refund($transaction_id, $transaction_amount, $description = '')
    {
        $status = '3';

        if ($transaction_id != '') {
            $intentData = [
                'previous_transaction_id' => $transaction_id,
                'transaction_amount'      => $transaction_amount,
                'description'             => $description,
            ];

            $this->result      = $this->post($intentData, '/v1/transactions/cc/refund/prev-trxn');
            $this->transaction = json_decode($this->result);

            if ($this->transaction->type == 'Transaction') {
                $status = $this->checkStatus($this->transaction->data->status_code);
            }
        }

        return $status;
    }

    /**
     * Refund an ACH transaction.
     */
    public function refundAch($transaction_id, $transaction_amount, $description = ''): mixed
    {
        if ($transaction_id != '') {
            $intentData = [
                'previous_transaction_id' => $transaction_id,
                'transaction_amount'      => $transaction_amount,
                'description'             => $description,
            ];

            $this->result      = $this->post($intentData, '/v1/transactions/ach/refund/prev-trxn');
            $this->transaction = json_decode($this->result);
        }

        return $this->transaction;
    }

    /**
     * Void a transaction.
     */
    public function void($transaction_id, $description = ''): mixed
    {
        if ($transaction_id != '') {
            $intentData = ['description' => $description];

            $this->result      = $this->put($intentData, "/v1/transactions/$transaction_id/void");
            $this->transaction = json_decode($this->result);
        }

        return $this->transaction;
    }

    /**
     * Complete (capture) a previously authorised transaction.
     */
    public function completeAuthTransaction(array $intentData, string $transactionID): bool|string|null
    {
        if (empty($transactionID)) {
            throw new \InvalidArgumentException('Transaction ID cannot be empty.');
        }

        return $this->patch($intentData, "/v1/transactions/$transactionID/auth-complete");
    }

    // ── ApiResponse Variants ───────────────────────────────────────────
    // These methods return standardized ApiResponse objects instead of
    // raw strings/ints. New code should prefer these over the legacy methods.

    /**
     * Refund a credit card transaction (standardized response).
     */
    public function refundWithResponse(
        string $transactionId,
        int $transactionAmount,
        string $description = '',
    ): ApiResponse {
        if (empty($transactionId)) {
            return ApiResponse::error(0, 'Transaction ID cannot be empty.');
        }
        if ($transactionAmount <= 0) {
            return ApiResponse::error(0, 'Transaction amount must be greater than zero.');
        }

        $intentData = [
            'previous_transaction_id' => $transactionId,
            'transaction_amount'      => $transactionAmount,
            'description'             => $description,
        ];

        return $this->callApiResponse('POST', $intentData, '/v1/transactions/cc/refund/prev-trxn');
    }

    /**
     * Refund an ACH transaction (standardized response).
     */
    public function refundAchWithResponse(
        string $transactionId,
        int $transactionAmount,
        string $description = '',
    ): ApiResponse {
        if (empty($transactionId)) {
            return ApiResponse::error(0, 'Transaction ID cannot be empty.');
        }
        if ($transactionAmount <= 0) {
            return ApiResponse::error(0, 'Transaction amount must be greater than zero.');
        }

        $intentData = [
            'previous_transaction_id' => $transactionId,
            'transaction_amount'      => $transactionAmount,
            'description'             => $description,
        ];

        return $this->callApiResponse('POST', $intentData, '/v1/transactions/ach/refund/prev-trxn');
    }

    /**
     * Void a transaction (standardized response).
     */
    public function voidWithResponse(string $transactionId, string $description = ''): ApiResponse
    {
        if (empty($transactionId)) {
            return ApiResponse::error(0, 'Transaction ID cannot be empty.');
        }

        return $this->callApiResponse('PUT', ['description' => $description], "/v1/transactions/$transactionId/void");
    }

    /**
     * Complete (capture) a previously authorised transaction (standardized response).
     */
    public function completeAuthWithResponse(array $intentData, string $transactionId): ApiResponse
    {
        if (empty($transactionId)) {
            return ApiResponse::error(0, 'Transaction ID cannot be empty.');
        }

        return $this->callApiResponse('PATCH', $intentData, "/v1/transactions/$transactionId/auth-complete");
    }

    /**
     * Get transaction details (standardized response).
     */
    public function getTransactionWithResponse(string $transactionId): ApiResponse
    {
        if (empty($transactionId)) {
            return ApiResponse::error(0, 'Transaction ID cannot be empty.');
        }

        return $this->callApiResponse('GET', [], "/v1/transactions/$transactionId?expand[0]=transaction_references");
    }

    /**
     * Delete a token (standardized response).
     */
    public function tokenDeleteWithResponse(string $tokenId): ApiResponse
    {
        if (empty($tokenId)) {
            return ApiResponse::error(0, 'Token ID cannot be empty.');
        }

        return $this->callApiResponse('DELETE', [], "/v1/tokens/$tokenId");
    }

    // ═══════════════════════════════════════════════════════════════════
    //  Account Vault / Tokens
    // ═══════════════════════════════════════════════════════════════════

    public function getAccountVault(string $accountId)
    {
        return json_decode($this->get([], "/v2/accountvaults/$accountId", true));
    }

    public function tokenGet(string $tokenID): string
    {
        return $this->get([], "/v1/tokens/$tokenID");
    }

    public function tokenDelete(string $tokenID): string
    {
        return $this->delete([], "/v1/tokens/$tokenID");
    }

    public function tokenList(array $intentData, array $filter): string
    {
        $filterString = '';
        if ($filter) {
            $filterString = '/endpoint?filter=' . json_encode($filter);
        }

        return $this->get($intentData, '/v1/tokens' . $filterString);
    }

    public function tokenCCUpdate(array $intentData, string $tokenID): string
    {
        return $this->patch($intentData, "/v1/tokens/$tokenID/cc");
    }

    // ═══════════════════════════════════════════════════════════════════
    //  Webhooks / Postbacks
    // ═══════════════════════════════════════════════════════════════════

    public function createAchPostback($url): string
    {
        $intentData = [
            'is_active'              => 'true',
            'on_create'              => 'true',
            'on_update'              => 'true',
            'on_delete'              => 'true',
            'location_id'            => $this->location_id,
            'product_transaction_id' => (string)$this->product_id_ach,
            'url'                    => (string)$url,
            'number_of_attempts'     => 1,
        ];

        return $this->createTransactionPostback($intentData);
    }

    public function createCCPostback($url): string
    {
        $intentData = [
            'is_active'              => 'true',
            'on_create'              => 'true',
            'on_update'              => 'true',
            'on_delete'              => 'true',
            'location_id'            => $this->location_id,
            'product_transaction_id' => (string)$this->product_id_cc,
            'url'                    => (string)$url,
            'number_of_attempts'     => 1,
        ];

        return $this->createTransactionPostback($intentData);
    }

    public function createTransactionPostback(array $intentData): string
    {
        return $this->post($intentData, '/v1/webhooks/transaction');
    }

    /**
     * Create a transaction postback with detailed response information.
     *
     * @return array{success: bool, data: object|null, message: string|null,
     *               statusCode: int|null, response: array|null, endpoint: string}
     */
    public function createTransactionPostbackWithDetails(array $intentData): array
    {
        $endpoint  = '/v1/webhooks/transaction';
        $apiResult = $this->callAPIWithDetails('POST', $intentData, $endpoint);

        if (!$apiResult['success']) {
            return [
                'success'    => false,
                'data'       => null,
                'message'    => $apiResult['message'],
                'statusCode' => $apiResult['statusCode'],
                'response'   => $apiResult['response'],
                'endpoint'   => $endpoint,
            ];
        }

        $response = $apiResult['response'];

        if (isset($response['data']['id'])) {
            return [
                'success'    => true,
                'data'       => (object)$response['data'],
                'message'    => null,
                'statusCode' => $apiResult['statusCode'],
                'response'   => $response,
                'endpoint'   => $endpoint,
            ];
        }

        return [
            'success'    => false,
            'data'       => null,
            'message'    => 'Unexpected response format',
            'statusCode' => $apiResult['statusCode'],
            'response'   => $response,
            'endpoint'   => $endpoint,
        ];
    }

    public function deleteTransactionPostback(string $webHookId): string
    {
        return $this->delete([], "/v1/webhooks/$webHookId");
    }

    // ═══════════════════════════════════════════════════════════════════
    //  Surcharge
    // ═══════════════════════════════════════════════════════════════════

    public function calculateSurcharge(array $intentData): string
    {
        $intentData['product_transaction_id'] = $this->product_id_cc;

        return $this->post($intentData, '/v1/public/calculate-surcharge');
    }

    // ═══════════════════════════════════════════════════════════════════
    //  Status Mapping
    // ═══════════════════════════════════════════════════════════════════

    public function checkStatus($statusCode): int
    {
        return match ((int)$statusCode) {
            101, 102, 111, 131, 132, 133, 134 => 1,
            201, 301, 331 => 2,
            default => 3,
        };
    }

    // ═══════════════════════════════════════════════════════════════════
    //  Batch API Calls
    // ═══════════════════════════════════════════════════════════════════

    /**
     * Execute multiple API calls as a batch and return all responses.
     *
     * If Guzzle is available and async is enabled, requests execute concurrently.
     * Otherwise, requests execute sequentially.
     *
     * @param array $calls Each element: ['method' => string, 'data' => array, 'endpoint' => string, 'v2' => bool]
     * @param bool $async Enable async/concurrent execution if Guzzle is available (default: true)
     *
     * @return ApiResponse[]
     */
    public function batchCall(array $calls, bool $async = true): array
    {
        $requests = [];
        foreach ($calls as $key => $call) {
            $v2      = $call['v2'] ?? false;
            $baseURL = $this->resolveBaseUrl($v2);

            $requests[$key] = [
                'method'  => $call['method'],
                'url'     => $baseURL . $call['endpoint'],
                'headers' => $this->buildHeaders(),
                'data'    => $call['data'] ?? [],
            ];
        }

        return $this->httpClient->batch($requests, $async);
    }

    // ═══════════════════════════════════════════════════════════════════
    //  Low-Level HTTP (uses PSR-18 FortisHttpClient)
    // ═══════════════════════════════════════════════════════════════════

    protected function callAPI(string $sendType, array $intentData, string $endPoint, bool $v2 = false): string
    {
        $baseURL = $this->resolveBaseUrl($v2);
        $url     = $baseURL . $endPoint;
        $headers = $this->buildHeaders();

        $response = $this->httpClient->request($sendType, $url, $headers, $intentData);

        return $response->rawBody ?? json_encode(['message' => 'Empty response from API']);
    }

    /**
     * Call the Fortis API and return a detailed structured response.
     *
     * @return array{success: bool, statusCode: int, response: array|null, message: string|null}
     */
    protected function callAPIWithDetails(
        string $sendType,
        array $intentData,
        string $endPoint,
        bool $v2 = false,
    ): array {
        $baseURL = $this->resolveBaseUrl($v2);
        $url     = $baseURL . $endPoint;
        $headers = $this->buildHeaders();

        $response = $this->httpClient->request($sendType, $url, $headers, $intentData);

        return [
            'success'    => $response->success,
            'statusCode' => $response->statusCode,
            'response'   => $response->data,
            'message'    => $response->message,
        ];
    }

    protected function post(array $intentData, string $endPoint): string
    {
        return $this->callAPI('POST', $intentData, $endPoint);
    }

    protected function get(array $intentData, string $endPoint, bool $v2 = false): string
    {
        return $this->callAPI('GET', $intentData, $endPoint, $v2);
    }

    protected function patch(array $intentData, string $endPoint): string
    {
        return $this->callAPI('PATCH', $intentData, $endPoint);
    }

    protected function put(array $intentData, string $endPoint): string
    {
        return $this->callAPI('PUT', $intentData, $endPoint);
    }

    protected function delete(array $intentData, string $endPoint): string
    {
        return $this->callAPI('DELETE', $intentData, $endPoint);
    }

    /**
     * Call the Fortis API and return a standardized ApiResponse directly.
     *
     * Unlike callAPI (returns raw string) or callAPIWithDetails (returns array),
     * this returns the typed ApiResponse DTO for use by the *WithResponse methods.
     */
    protected function callApiResponse(string $method, array $data, string $endPoint, bool $v2 = false): ApiResponse
    {
        $baseURL = $this->resolveBaseUrl($v2);
        $url     = $baseURL . $endPoint;
        $headers = $this->buildHeaders();

        return $this->httpClient->request($method, $url, $headers, $data);
    }

    /**
     * Make Level 3 API call, trying each endpoint until one succeeds.
     */
    protected function postLevel3(array $intentData, array $endPoints, string $cardType): string
    {
        $this->logger->info('Starting Level 3 API call', [
            'endpoints_to_try' => $endPoints,
            'user_id'          => $this->user_id,
            'developer_id'     => $this->developerId,
            'payload'          => $intentData,
        ]);

        $response = null;
        foreach ($endPoints as $endPoint) {
            $url = $this->resolveBaseUrl() . $endPoint;

            $this->logger->info('Attempting Level 3 call: ' . $endPoint, [
                'url'     => $url,
                'headers' => [
                    'Content-Type: application/json',
                    'user-id'      => $this->user_id,
                    'user-api-key' => '***',
                    'developer-id' => $this->developerId,
                ],
            ]);

            $response        = $this->callAPI('POST', $intentData, $endPoint);
            $decodedResponse = json_decode($response, true);

            $this->logger->info('Level 3 response for ' . $endPoint, [
                'response_body' => $response,
            ]);

            if ($decodedResponse && (!isset($decodedResponse['type']) || $decodedResponse['type'] !== 'Error')) {
                $this->logger->info('Level 3 call succeeded with endpoint: ' . $endPoint);

                return $response;
            }

            $responseCode = $decodedResponse['response_code'] ?? null;
            if ($responseCode === 404) {
                $this->logger->info('Endpoint not found, trying next: ' . $endPoint);
                continue;
            }
            break;
        }

        return $response ?: json_encode([
                                            'type'    => 'Error',
                                            'message' => 'All Level 3 endpoints failed',
                                            'detail'  => 'No valid Level 3 endpoint found for ' . $cardType,
                                        ]);
    }

    // ═══════════════════════════════════════════════════════════════════
    //  Helpers
    // ═══════════════════════════════════════════════════════════════════

    protected function buildHeaders(): array
    {
        return [
            'Content-Type' => 'application/json',
            'user-id'      => $this->user_id,
            'user-api-key' => $this->user_api_key,
            'developer-id' => $this->developerId,
        ];
    }

    protected function resolveBaseUrl(bool $v2 = false): string
    {
        return $this->config->getApiUrl($v2);
    }
}
