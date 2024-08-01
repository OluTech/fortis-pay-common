<?php

namespace Fortis\Api;

use Fortis\FrameworkApi\FortisFrameworkApi;

class FortisApi
{

    public const FORTIS_URL_SANDBOX    = "https://api.sandbox.fortis.tech";
    public const FORTIS_URL_PRODUCTION = "https://api.fortis.tech";
    public const CONTENT_TYPE          = 'Content-Type: application/json';

    public const WHITE        = '#ffffff';
    public const BLACK        = '#000000';
    public const COLOURBUTTON = '#0700ff';
    public const COLOURLINK   = '#0000ff';

    public const TEST_MODE = 'test_mode';
    public const VAULT     = 'vault';

    public const TOKENIZATION                  = 'Tokenization';
    public const ACH                           = 'ach';
    public const CC                            = 'cc';
    public const LEVEL3                        = 'level3';
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

    public $user_id;
    public $user_api_key;
    public $location_id;
    public $product_id_cc;
    public $product_id_ach;
    public $action;
    public $id;
    public $result;
    public $transactionType;
    public $paymentMethod;
    public $framework;
    public $token;
    public $transaction;
    private string $developerId;
    private string $fortisUrl;

    public function __construct($id, $data = [])
    {
        if ($id != null) {
            $this->id           = $id;
            $this->framework    = new FortisFrameworkApi($id, $data);
            $this->user_id      = $this->framework->getUserId();
            $this->user_api_key = $this->framework->getUserApiKey();
            $this->location_id  = $this->framework->getLocationId();
            if ($this->framework->getACHEnabled()) {
                $this->product_id_ach = $this->framework->getProductIdACH();
            }
            if ($this->framework->getCCEnabled()) {
                $this->product_id_cc = $this->framework->getProductIdCC();
            }
            $this->action = $this->framework->getAction();
        }

        if ($this->framework->getEnvironment() === 'production') {
            $this->developerId = $this->framework::DEVELOPER_ID_PRODUCTION;
            $this->fortisUrl   = self::FORTIS_URL_PRODUCTION;
        } else {
            $this->developerId = $this->framework::DEVELOPER_ID_SANDBOX;
            $this->fortisUrl   = self::FORTIS_URL_SANDBOX;
        }
    }

    /**
     * add level 3 data to transaction
     *
     * @param array $lineItems
     *
     * @return string
     */
    public function addLevel3(array $lineItems): string
    {
        $account_type  = $this->transaction->account_type;
        $transactionId = $this->transaction->id;

        $intentData                              = [];
        $intentData['level3_data']               = [];
        $intentData['level3_data']['line_items'] = [];

        $result = '';

        if ($account_type === 'visa') {
            $intentData['level3_data']['tax_amount'] = $this->transaction->tax;

            foreach ($lineItems as $lineItem) {
                $item = [
                    "description"    => (string)$lineItem['description'],
                    "commodity_code" => (string)$lineItem['commodity_code'],
                    "product_code"   => (string)$lineItem['product_code'],
                    "unit_code"      => (string)$lineItem['unit_code'],
                    "unit_cost"      => (int)$lineItem['unit_cost'],
                    "quantity"       => 1,
                ];
                if (isset($lineItem['quantity'])) {
                    $item['quantity'] = $lineItem['quantity'];
                }
                $intentData['level3_data']['line_items'][] = $item;
            }

            $result = $this->post($intentData, '/v1/transactions/' . $transactionId . '/level3/visa');
        } elseif ($account_type === 'mc') {
            $intentData['level3_data']['tax_amount'] = $this->transaction->tax;
            foreach ($lineItems as $lineItem) {
                $item = [
                    "description"  => (string)$lineItem['description'],
                    "product_code" => (string)$lineItem['product_code'],
                    "unit_code"    => (string)$lineItem['unit_code'],
                    "unit_cost"    => (int)$lineItem['unit_cost'],
                    'quantity'     => 1,
                ];
                if (isset($lineItem['quantity'])) {
                    $item['quantity'] = $lineItem['quantity'];
                }
                $intentData['level3_data']['line_items'][] = $item;

                $result = $this->post($intentData, '/v1/transactions/' . $transactionId . '/level3/master-card');
            }
        }

        return $result;
    }

    /**
     *
     *
     * @param $total
     * @param $tax_amount
     * @param $saveAccount
     *
     * @return mixed
     */
    public function getClientToken($total, $tax_amount, $saveAccount): mixed
    {
        $intentData = [
            'action'       => $this->action,
            'amount'       => (int)$total,
            'location_id'  => $this->location_id,
            'save_account' => $saveAccount,

        ];

        if ($this->framework->retainedAmountEnabled()) {
            if ($this->framework->useFixedRetainedAmount()) {
                $retainedAmount = (int)(100 * $this->framework->getRetainedAmountValue());
            } else {
                $retainedAmount = (int)($total * $this->framework->getRetainedAmountValue() / 100);
            }

            $intentData['secondary_amount'] = min($retainedAmount, $total);
        }

        if ($tax_amount > 0) {
            $intentData['tax_amount'] = (int)$tax_amount;
        }
        $intentData['methods'] = [];

        if ($this->product_id_cc) {
            $intentData['methods'][] = [
                "type"                   => "cc",
                "product_transaction_id" => $this->product_id_cc
            ];
        }
        if (isset($this->product_id_ach)) {
            $intentData['methods'][] = [
                "type"                   => "ach",
                "product_transaction_id" => $this->product_id_ach
            ];
        }

        $response = json_decode($this->post($intentData, "/v1/elements/transaction/intention"));

        if (!isset($response->data->client_token)) {
            if (isset($response?->type) && $response->type === 'Error') {
                return ['error' => $response?->detail];
            } else {
                return ['error' => 'An unknown error occurred'];
            }
        } else {
            return $response->data->client_token;
        }
    }

    /**
     *
     *
     * @return string
     */
    public function getPaymentMethodToken(): string
    {
        $intentData = [
            'action'      => 'tokenization',
            'location_id' => $this->location_id,

        ];

        $intentData['methods'] = [];

        if ($this->product_id_cc) {
            $intentData['methods'][] = [
                "type"                   => "cc",
                "product_transaction_id" => $this->product_id_cc
            ];
        }
        if (isset($this->product_id_ach)) {
            $intentData['methods'][] = [
                "type"                   => "ach",
                "product_transaction_id" => $this->product_id_ach
            ];
        }


        $response = json_decode($this->post($intentData, "/v1/elements/transaction/intention"));
        if ($response->data->client_token == null) {
            return '';
        } else {
            return $response->data->client_token;
        }
    }

    /**
     * @param string $transactionId
     *
     * @return string
     */
    public function getTransaction(string $transactionId)
    {
        return $this->get([], "/v1/transactions/$transactionId");
    }

    /**
     * @param $_POST
     * @param $transaction amount int * 100
     * @param $customer_id
     *
     * @return string
     */
    public function processTransaction($post, $transaction_amount, $customer_id)
    {
        $this->result          = json_decode(stripslashes($post['result']));
        $this->transaction     = isset($this->result->data) ? $this->result->data : null;
        $saved_account         = isset($this->transaction->saved_account) ? $this->transaction->saved_account : null;
        $useSavedAccount       = isset($post['useSavedAccount']) ? $post['useSavedAccount'] == 'on' : false;
        $saveAccount           = isset($post['SaveAccount']) ? $post['SaveAccount'] == 'on' : false;
        $token_id              = isset($saved_account->id) ? $saved_account->id : null;
        $this->transactionType = $this->getTransactionType($this->transaction);
        $this->paymentMethod   = $this->transaction->payment_method;
        if ($useSavedAccount && isset($post['CC'])) {
            $token_id = $post['CC'];
        }


        if (isset($this->transaction->id) || isset($token_id)) {
            $status = 2;
            if ($this->transactionType == 'error') {
                $status = 3;
            } elseif ($useSavedAccount) {
                $status = $this->doTokenisedTransaction($transaction_amount, $token_id);
            } elseif ($this->transaction) {
                if ($this->framework->vaultEnabled() && $saveAccount && $token_id != null) {
                    $this->framework->vaultCard($token_id, $saved_account, $customer_id);
                }
                if ($this->transactionType == 'sale' || $this->transactionType = 'auth-only') {
                    $status = $this->checkStatus($this->transaction->status_code);
                }
            }
        } else {
            $status = 2;
        }

        if ($this->paymentMethod == 'ach' && $status == 1) {
            $status = 5;
        }

        return $status;
    }

    /**
     * @param
     * @param $transaction
     * @param
     *
     * @return string
     */
    public function getTransactionType($transaction)
    {
        $this->transaction = $transaction;
        $this->action      = isset($this->transaction->{'@action'}) ? $this->transaction->{'@action'} : null;

        return isset($this->transaction->type) ? $this->transaction->type : $this->action;
    }

    /**
     * @param $transaction amount int * 100
     * @param $token_id
     *
     * @return string
     */
    public function doTokenisedTransaction($transaction_amount, $token_id, $order_id, $tax_amount = 0): string
    {
        $token                = $this->framework->getTokenById($token_id);
        $productTransactionId = $this->framework->getProductIdCC();
        if (!in_array($token[0]['type'], ['cc', 'visa'])) {
            $productTransactionId = $this->framework->getProductIdACH();
            $this->action         = 'ach';
        }


        $intentData = [
            'transaction_amount'     => $transaction_amount,
            'token_id'               => $token[0]['token'],
            'product_transaction_id' => $productTransactionId,
            'tax'                    => $tax_amount,
            'description'            => (string)$order_id,
        ];

        if ($this->framework->retainedAmountEnabled()) {
            if ($this->framework->useFixedRetainedAmount()) {
                $retainedAmount = (int)(100 * $this->framework->getRetainedAmountValue());
            } else {
                $retainedAmount = (int)($transaction_amount * $this->framework->getRetainedAmountValue() / 100);
            }

            $intentData['secondary_amount'] = min($retainedAmount, $retainedAmount);
        }


        if ($this->action == 'sale') {
            $transactionResult = $this->post($intentData, "/v1/transactions/cc/sale/token");
        } elseif ($this->action == 'ach') {
            $transactionResult = $this->post($intentData, "/v1/transactions/ach/debit/token");
        } else { //auth-only
            $transactionResult = $this->post($intentData, "/v1/transactions/cc/auth-only/token");
        }

        $this->transaction = json_decode($transactionResult, true)['data'];
        $this->result      = $this->transaction;
        if ($this->transaction['id']) {
            $status = $this->checkStatus($this->transaction['status_code']);
        } else {
            $status = '2';
        }

        return $status;
    }

    public function refund($transaction_id, $transaction_amount)
    {
        $status = '3';

        if ($transaction_id != '') {
            $intentData = [
                'previous_transaction_id' => $transaction_id,
                'transaction_amount'      => $transaction_amount
            ];


            $this->result      = $this->post($intentData, "/v1/transactions/cc/refund/prev-trxn");
            $this->transaction = json_decode($this->result);

            if ($this->transaction->type == 'Transaction') {
                $status = $this->checkStatus($this->transaction->data->status_code);
            }
        }

        return $status;
    }

    public function createAchPostback($url, $transaction_id)
    {
        $intentData = [
            'is_active'              => 'true',
            "on_create"              => 'true',
            "on_update"              => 'true',
            "on_delete"              => 'true',
            'location_id'            => $this->location_id,
            "product_transaction_id" => (string)$this->transaction->product_transaction_id,
            "url"                    => (string)$url,
            "number_of_attempts"     => 1,
        ];

        $result = $this->createTransactionPostback($intentData);
    }

    public function createTransactionPostback(array $intentData)
    {
        return $this->post($intentData, "/v1/webhooks/transaction");
    }


    public function tokenCCUpdate(array $intentData, string $tokenID)
    {
        return $this->patch($intentData, "/v1/tokens/$tokenID/cc");
    }

    public function tokenCCDelete(string $tokenID)
    {
        return $this->delete([], "/v1/tokens/$tokenID");
    }

    public function tokenCCList(array $intentData, array $filter)
    {
        $filterString = '';
        if ($filter) {
            $filterString = '/endpoint?filter=' . json_encode($filter);
        }

        return $this->get($intentData, "/v1/tokens" . $filterString);
    }

    public function checkStatus($statusCode)
    {
        switch ($statusCode) {
            case 101: // sale
                $status = 1;
                break;
            case 102: // authonly
                $status = 1;
                break;
            case 111: // Refund cc Refunded
                $status = 1;
                break;
            case 201: // voided
                $status = 2;
                break;
            case 301: // declined
                $status = 2;
                break;
            case 331: // charged back
                $status = 2;
                break;
            case 131: // Pending Origination
                $status = 1;
                break;
            case 132: // Originating
                $status = 1;
                break;
            case 133: // Originated
                $status = 1;
                break;
            case 134: // settled
                $status = 1;
                break;

            default:
                $status = 3;
                break;
        }

        return $status;


        // 121 - Credit/Debit/Refund cc AvsOnly

        // 131 - Credit/Debit/Refund ach Pending Origination

        // 132 - Credit/Debit/Refund ach Originating

        // 133 - Credit/Debit/Refund ach Originated

        // 134 - Credit/Debit/Refund ach Settled

        // 191 - Settled (depracated - batches are now settled on the /v2/transactionbatches endpoint)

        // 201 - All cc/ach Voided

        // 301 - All cc/ach Declined

        // 331 - Credit/Debit/Refund ach Charged Back
    }

    /**
     * @param string $sendType "POST","DELETE',"GET","PATCH"
     * @param array $intentData
     * @param string $endpoint
     *
     * @return bool|string|null
     */
    private function callAPI(string $sendType, array $intentData, string $endPoint): string
    {
        $url  = $this->fortisUrl . $endPoint;
        $curl = curl_init($url);
        curl_setopt_array(
            $curl,
            [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING       => "",
                CURLOPT_MAXREDIRS      => 10,
                CURLOPT_TIMEOUT        => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST  => $sendType,
                CURLOPT_POSTFIELDS     => json_encode($intentData),
                CURLOPT_HTTPHEADER     => [
                    self::CONTENT_TYPE,
                    "user-id: $this->user_id",
                    "user-api-key: $this->user_api_key",
                    "developer-id: $this->developerId",
                ],
            ]
        );
        $cnt           = 0;
        $intentCreated = false;
        $curlError     = null;
        $response      = null;
        while (!$intentCreated && $cnt < 5) {
            $response     = curl_exec($curl);
            $responseCode = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
            if ($responseCode !== 200) {
                $curlError = curl_error($curl);
                $cnt++;
            }
            $intentCreated = true;
        }

        if (!$intentCreated) {
            FortisFrameworkApi::logError('FortisApi->callAPI', $curlError);

            // Do something with this error
            return $curlError;
        }

        return $response;
    }

    private function post(array $intentData, string $endPoint): string
    {
        return $this->callAPI("POST", $intentData, $endPoint);
    }

    private function get(array $intentData, string $endPoint): string
    {
        return $this->callAPI("GET", $intentData, $endPoint);
    }

    private function patch(array $intentData, string $endPoint)
    {
        return $this->callAPI("PATCH", $intentData, $endPoint);
    }

    private function put(array $intentData, string $endPoint)
    {
        return $this->callAPI("PUT", $intentData, $endPoint);
    }

    private function delete(array $intentData, string $endPoint): string
    {
        return $this->callAPI("DELETE", $intentData, $endPoint);
    }
}
