# FortisPay Common Class for modules

## Installation

You can install this package using composer:

```console
composer require fortispay/fortis-pay-common
```

## Requirements

The Fortis Common Library requires the following to function:

### Core Dependencies

- **PHP**: 8.0 or higher
- **PSR-18 HTTP Client**: Any PSR-18 compliant HTTP client (e.g., GuzzleHttp)
- **PSR-7 Factories**: Request and Stream factory implementations
- **PSR-3 Logger**: Any PSR-3 compliant logger implementation

### Platform-Specific Implementation

You must provide implementations for:

- **ConfigProviderInterface**: For configuration/credentials management
- **TokenStorageInterface**: For storing vaulted payment methods

### Optional for Async Batch Operations

- **GuzzleHttp**: For concurrent batch API requests via promises
- **guzzlehttp/promises**: For async promise support

## Core Classes

### FortisApi.php

This is the central gateway for integrating with the Fortis Payment API. It is platform-agnostic and requires:

- A ConfigProvider implementation
- A TokenStorage implementation
- An HTTP client (PSR-18)
- A logger (PSR-3)

### FortisFrameworkApi.php

This is an example framework-specific adapter that shows how to adapt the Fortis Common Library to a particular
framework.

## Platform Adapters

The Fortis Common Library requires platform-specific adapter implementations to function. These are provided via two
main interfaces:

### ConfigProvider Implementation

The `ConfigProviderInterface` must be implemented to provide Fortis API credentials and configuration settings.

**See:** `examples/ConfigProvider.php` for a complete, copy-paste ready example.

**Required Configuration Keys:**

- `environment`: `'sandbox'` or `'production'` (default: sandbox)
- `sandbox_user_id`: Sandbox Fortis user ID
- `sandbox_user_api_key`: Sandbox Fortis API key
- `sandbox_location_id`: Sandbox Fortis location ID
- `sandbox_product_id_cc`: Sandbox product ID for credit cards
- `sandbox_product_id_ach`: Sandbox product ID for ACH
- `production_user_id`: Production Fortis user ID
- `production_user_api_key`: Production Fortis API key
- `production_location_id`: Production Fortis location ID
- `production_product_id_cc`: Production product ID for credit cards
- `production_product_id_ach`: Production product ID for ACH
- `developer_id`: Fortis developer ID (optional)
- `action`: `'sale'` or `'auth-only'` (default: sale)
- `ach_enabled`: boolean (default: false)
- `cc_enabled`: boolean (default: true)
- `vault_enabled`: boolean (default: false)
- `level3_enabled`: boolean (default: false)

**Required Methods:**

```php
use Fortis\Api\Contracts\ConfigProviderInterface;

class ConfigProvider implements ConfigProviderInterface
{
    public function getUserId(): string;                    // Merchant user ID
    public function getUserApiKey(): string;                // Merchant API key
    public function getLocationId(): string;                // Fortis location ID
    public function getProductIdCC(): string;               // Product ID for credit cards
    public function getProductIdACH(): string;              // Product ID for ACH
    public function getEnvironment(): string;               // 'sandbox' or 'production'
    public function getDeveloperId(): string;               // Developer ID
    public function getApiUrl(bool $v2 = false): string;   // API base URL
    public function getAction(): string;                    // 'sale' or 'auth-only'
    public function isACHEnabled(): bool;                   // ACH enabled?
    public function isCCEnabled(): bool;                    // Credit card enabled?
    public function isVaultEnabled(): bool;                 // Vaulting enabled?
    public function isLevel3Enabled(): bool;                // Level 3 enabled?
    public function get(string $key, mixed $default = null): mixed; // Get arbitrary config
}
```

**Example Usage:**

```php
// Copy examples/ConfigProvider.php to your application
use App\Fortis\Adapters\ConfigProvider;

$config = [
    'environment' => 'sandbox',
    'sandbox_user_id' => 'user_abc123',
    'sandbox_user_api_key' => 'key_xyz789',
    'sandbox_location_id' => 'loc_123',
    'sandbox_product_id_cc' => 'prod_cc_123',
    'sandbox_product_id_ach' => 'prod_ach_456',
    'developer_id' => 'dev_123',
    'cc_enabled' => true,
    'ach_enabled' => false,
];

$provider = new ConfigProvider($config);
echo $provider->getUserId();        // Output: user_abc123
echo $provider->getEnvironment();   // Output: sandbox
```

### TokenStorage Implementation

The `TokenStorageInterface` must be implemented to manage stored payment method tokens.

**See:** `examples/TokenStorage.php` for a complete, copy-paste ready example.

**Required Methods:**

```php
use Fortis\Api\Contracts\TokenStorageInterface;

interface TokenStorageInterface
{
    /**
     * Retrieve a Fortis token by platform vault ID
     * @return string|null Fortis token or null if not found
     */
    public function getTokenById(string $id): ?string;

    /**
     * Get the payment type (cc, ach, etc.) for a stored token
     * @return string|null Payment type or null if not found
     */
    public function getPaymentTypeById(string $id): ?string;

    /**
     * Store a new vaulted payment method
     * @param string $tokenId Fortis token ID
     * @param array $cardData Card data from Fortis (payment_method, last_four, first_six, etc.)
     * @param string $customerId Platform customer ID
     */
    public function vaultCard(string $tokenId, array $cardData, string $customerId): void;

    /**
     * Delete a stored token
     */
    public function deleteToken(string $id): void;
}
```

**Example Usage:**

```php
// Copy examples/TokenStorage.php to your application
use App\Fortis\Adapters\TokenStorage;

$storage = new TokenStorage();

// Store a vaulted card
$storage->vaultCard(
    'tok_abc123',
    [
        'payment_method' => 'cc',
        'last_four' => '4242',
        'first_six' => '424242',
    ],
    'customer_123'
);

// Retrieve token
$token = $storage->getTokenById('vault_xyz'); // Returns: tok_abc123
$type = $storage->getPaymentTypeById('vault_xyz');  // Returns: cc

// Delete token
$storage->deleteToken('vault_xyz');
```

**Production Implementation Notes:**

When implementing TokenStorage for production, consider:

- **Database Storage**: Store tokens in encrypted database tables
- **Encryption**: Encrypt Fortis tokens at rest using industry-standard encryption
- **TTL/Expiration**: Implement token expiration and cleanup
- **Audit Logging**: Log all token operations for compliance
- **Access Control**: Restrict token access to authorized users only
- **Backup Strategy**: Plan for token recovery and backups
- **PCI Compliance**: Ensure compliance with payment card industry standards

## FortisHttpClient Constructor

The `FortisHttpClient` requires the following dependencies in its constructor:

```php
public function __construct(
    ClientInterface $httpClient,           // PSR-18 HTTP client (e.g., GuzzleHttp\Client)
    RequestFactoryInterface $requestFactory,  // PSR-17 Request factory
    StreamFactoryInterface $streamFactory,    // PSR-17 Stream factory  
    LoggerInterface $logger,                 // PSR-3 Logger
    ?RetryHandler $retryHandler = null,      // Optional retry handler
)
```

**Parameters:**

- `$httpClient` (PSR-18): Any PSR-18 compliant HTTP client (GuzzleHttp recommended)
- `$requestFactory` (PSR-17): For creating HTTP request objects
- `$streamFactory` (PSR-17): For creating HTTP stream bodies
- `$logger` (PSR-3): Any PSR-3 compliant logger (e.g., Monolog, Laravel Logger)
- `$retryHandler` (optional): For automatic retry logic on failed requests

**Example Setup:**

```php
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Psr7\HttpFactory;
use Monolog\Logger;
use Monolog\Handlers\StreamHandler;

// Create PSR-3 logger
$logger = new Logger('fortis');
$logger->pushHandler(new StreamHandler('php://stderr'));

// Create HTTP client and factories
// GuzzleHttp\Psr7\HttpFactory implements both PSR-17 interfaces (RequestFactory + StreamFactory)
$httpClient = new GuzzleClient();
$httpFactory = new HttpFactory();

// Create FortisHttpClient
$fortisHttpClient = new FortisHttpClient(
    $httpClient,
    $httpFactory,  // implements RequestFactoryInterface
    $httpFactory,  // implements StreamFactoryInterface
    $logger
);
```

> **Alternative**: You can use separate `Http\Factory\Guzzle\RequestFactory` and `Http\Factory\Guzzle\StreamFactory`
> classes if you prefer, but `GuzzleHttp\Psr7\HttpFactory` is simpler as it implements both PSR-17 interfaces.

## Breaking Changes from 0.x.x

### Version 1.x.x - Platform-Agnostic Refactoring

This version introduces a major architectural change to make the library platform-agnostic and PSR-compliant.

### Old Constructor (0.x.x) - NO LONGER WORKS

```php
$api = new FortisApi($id, $config_data);
```

### New Constructor (1.x.x) - REQUIRED

```php
$configProvider = new YourPlatformConfigProvider($settings);
$httpClient = new FortisHttpClient($guzzleClient, $requestFactory, $streamFactory, $logger);
$logger = new Monolog\Logger('fortis');
$tokenStorage = new YourPlatformTokenStorage();

$api = new FortisApi($configProvider, $httpClient, $logger, $tokenStorage);
```

### Migration Steps

1. **Copy adapter examples to your platform repository:**
    - Copy `examples/ConfigProvider.php` to your platform repository
    - Copy `examples/TokenStorage.php` to your platform repository

2. **Implement platform-specific adapters:**
    - Extend `ConfigProviderInterface` to load configuration from your platform's system
    - Extend `TokenStorageInterface` to store/retrieve tokens using your platform's storage
    - See "Platform Adapters" section below for details

3. **Update initialization:**
    - Create instances of your ConfigProvider and TokenStorage
    - Create FortisHttpClient with PSR-18/PSR-7 dependencies
    - Create FortisApi with these instances

4. **Update all FortisApi instantiation code:**
    - Old: `$api = new FortisApi($id, $config_data);`
    - New: `$api = new FortisApi($configProvider, $httpClient, $logger, $tokenStorage);`

### Why This Matters

- **Platform-agnostic**: Works with any framework (Laravel, Symfony, WooCommerce, BigCommerce, etc.)
- **PSR-compliant**: Uses standard PHP interfaces (PSR-3, PSR-7, PSR-18)
- **Testable**: Dependencies can be injected for easy testing
- **Maintainable**: Clear separation of concerns makes upgrades straightforward
- **Async support**: Enables concurrent batch operations with Guzzle promises

