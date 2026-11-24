# Changelog

## [[1.0.2]](https://github.com/OluTech/fortis-pay-common/releases/tag/1.0.2)

### Fixed

- A saved bank account is now always debited as a sale (`/v1/transactions/ach/debit/token`), and sent with
  `ach_sec_code: 'WEB'`. Before, a store set to authorise only posted to `/v1/transactions/ach/auth-only/token`, which
  Fortis answers with 404.
- A client token asked for a bank form alone is now a sale, even when the store authorises only. Fortis answers 424 to an
  auth-only intention for ACH alone.
- `refund()` and `doTokenisedTransaction()` read the status from `status_code` or, where the gateway sends it there
  (PayPal/Venmo), from `status_id`.

### Added

#### API Methods

- `tokenCreateFromTransaction()` - Create a token from a completed transaction (`POST /v1/tokens/previous-transaction`).
- `FortisApi::postbackAlreadyActive()` - Whether Fortis refused a postback because the same one is already active.
- `FortisApi::resolveStatusCode()` and `FortisApi::isPayPalVenmo()` - Read a transaction's status, and tell PayPal/Venmo
  transactions.
- `buildClientTokenIntent()` and `parseClientTokenResponse()` (protected) - What a client token asks Fortis for, and how its answer is
  read, so that a platform whose checkout asks for something different can adjust them.

#### Parameters

- `getClientToken()` takes an optional trailing `$contactId`; `doTokenisedTransaction()` takes optional trailing
  `$contactId`. Both are sent as `contact_id` when given.
- `checkStatus()` takes an optional `$reasonCodeId`.

#### Alternative payment methods (PayPal/Venmo)

- Optional: `apm_enabled`, `sandbox_product_id_apm` and `production_product_id_apm` are read through
  `ConfigProviderInterface::get()`, so `ConfigProviderInterface` is unchanged. When enabled and given a product, the
  client token offers an `apm` method (never together with a bank form alone).
- `checkStatus()` maps status 151 (sale: 4003 completed = 1, 4002 declined = 2, anything else = 5), 152 (order started =
  5) and 161 (refunded = 1). 5 means awaiting a result; no other status returns it.
- New public `$product_id_apm`, and constants `APM`, `SANDBOX_PRODUCT_ID_APM`, `PRODUCTION_PRODUCT_ID_APM`.

### Changed

#### Tokenised Transactions

- `doTokenisedTransaction()` and `refund()` pass the transaction's `reason_code_id` to `checkStatus()`, and may now
  return 5 (awaiting a result) for a PayPal/Venmo payment that has not yet completed.

## [[1.0.1]](https://github.com/OluTech/fortis-pay-common/releases/tag/1.0.1)

### Added

#### API Methods

- `getLocationProductTransactions()` - Retrieve product transactions for the configured location with expanded details.
- `getTokenStorage()` - Public accessor for the token storage instance.

#### Configuration

- `isGooglepayEnabled()` method added to `ConfigProviderInterface`.
- `isApplepayEnabled()` method added to `ConfigProviderInterface`.

### Changed

#### Token Storage

- `TokenStorageInterface::vaultCard()` now returns `string[]` (array of deleted duplicate Fortis token IDs) instead of
  `void`.
- `vaultCard()` is now responsible for removing duplicates before storing, returning deleted token IDs for API-level
  cleanup by the caller.

#### Configuration Keys (Documentation)

- Renamed `sandbox_api_key` to `sandbox_user_api_key` for clarity.
- Renamed `production_api_key` to `production_user_api_key` for clarity.

#### Documentation

- Simplified PSR-17 factory usage in README: recommend `GuzzleHttp\Psr7\HttpFactory` (implements both
  `RequestFactoryInterface` and `StreamFactoryInterface`) instead of separate `Http\Factory\Guzzle\RequestFactory` and
  `Http\Factory\Guzzle\StreamFactory`.

## [[1.0.0]](https://github.com/OluTech/fortis-pay-common/releases/tag/1.0.0)

**Major Release:** Platform-Agnostic PSR-Compliant Refactoring.

### BREAKING CHANGES

#### Constructor Signature

- Constructor signature changed to require dependency injection.
- Old: `new FortisApi($id, $data)`.
- New: `new FortisApi($configProvider, $httpClient, $logger, $tokenStorage)`.

#### Required Dependencies

- Must now provide `ConfigProviderInterface` implementation for configuration/credentials.
- Must now provide PSR-18 compatible HTTP client (e.g., GuzzleHttp).
- Must now provide PSR-3 compatible logger (e.g., Monolog).
- Must now provide `TokenStorageInterface` implementation for vault operations (or null for NullTokenStorage).

#### Configuration Management

- Configuration no longer loaded via FortisFrameworkApi.
- All configuration must be provided through ConfigProviderInterface.
- Platform adapters must implement their own configuration management.

### Added

#### Core Interfaces

- `ConfigProviderInterface` - Platform-agnostic configuration contract.
- `TokenStorageInterface` - Platform-agnostic token storage contract.
- `ApiResponse` DTO - Standardized response object for all API operations.

#### New Classes

- `FortisHttpClient` - PSR-18 HTTP client wrapper with retry logic.
- `RetryHandler` - Intelligent retry logic with exponential backoff (3 retries, configurable).
- `NullTokenStorage` - Null object pattern implementation for optional token storage.

#### Example Implementations

- `examples/ConfigProvider.php` - Template for platform-specific config providers.
- `examples/TokenStorage.php` - Template for platform-specific token storage.
- `examples/FortisFrameworkApi.php` - Updated template showing integration pattern.

#### API Enhancements

- New `*WithResponse()` methods for all transaction endpoints (e.g., `refundWithResponse()`, `voidWithResponse()`).
- `batchCall()` - Batch request support with async/fallback capabilities.
- Async batch operations with Guzzle Promise integration (automatic fallback to sequential).
- Intelligent retry logic with exponential backoff for transient errors (429, 503, 5xx).
- Comprehensive input validation on all transaction methods.
- Standardized error responses via ApiResponse DTO.

#### PSR Standards Compliance

- PSR-3 - Structured logging with context throughout.
- PSR-7 - HTTP message abstraction for requests/responses.
- PSR-18 - HTTP client interface for platform flexibility.
- PSR-17 - HTTP factory interfaces for request/stream creation.

### Changed

#### Architecture

- Refactored FortisApi to use clean dependency injection pattern.
- Removed all hardcoded dependencies - everything is now injectable.
- Platform-specific logic moved to adapter pattern (ConfigProviderInterface, TokenStorageInterface).

#### HTTP Communication

- HTTP client abstracted from direct curl to PSR-18 interface.
- All requests now use standardized ApiResponse format.
- Response parsing centralized with consistent error handling.

#### Logging

- Logging abstracted from hardcoded methods to PSR-3 interface.
- All operations now log with structured context (transaction IDs, amounts, errors, etc.).
- Supports any PSR-3 logger (Monolog, PSR Logger, Sentry, etc.).

#### Configuration Management

- All configuration centralized in ConfigProviderInterface.
- Environment-specific configuration (sandbox vs. production) delegated to provider.
- API URL resolution delegated to provider for flexibility.

#### Response Handling

- All responses now standardized through ApiResponse DTO.
- Legacy methods updated to use new infrastructure internally.
- New `*WithResponse()` methods provide typed responses.

### Removed

#### Platform Coupling

- Direct FortisFrameworkApi dependency removed.
- No more platform-specific framework references.
- Removed hardcoded platform constants (DEVELOPER_ID_PRODUCTION, DEVELOPER_ID_SANDBOX, etc.).
- Removed framework-specific initialization requirements.

#### HTTP Implementation

- Direct curl usage removed.
- All HTTP operations now delegated to PSR-18 client.

#### Hardcoded Configuration

- Removed hardcoded API URLs (now provided by ConfigProviderInterface).
- Removed hardcoded logging (now uses PSR-3).
- Removed hardcoded token storage (now injectable via TokenStorageInterface).

### Fixed

#### Exception Handling

- Improved exception handling in async batch operations.
- Proper error recovery for promise-based requests.
- Comprehensive error logging with context.

#### Input Validation

- Added validation on all transaction methods before API calls.
- Early return for invalid inputs with appropriate error messages.
- Prevents invalid API requests from reaching the API.

#### Level 3 Processing

- Improved error handling for Level 3 data submission.
- Card-type specific validation (Visa, MasterCard).
- Better error messages for unsupported card types.

### Migration Guide

#### Step 1: Create ConfigProvider

```php
$config = new YourPlatformConfigProvider($settings);
```

#### Step 2: Create HTTP Client

```php
$httpClient = new FortisHttpClient($guzzleClient, $requestFactory, $streamFactory, $logger);
```

#### Step 3: Create Token Storage (Optional)

```php
$tokenStorage = new YourPlatformTokenStorage();
```

#### Step 4: Instantiate FortisApi

```php
$api = new FortisApi($config, $httpClient, $logger, $tokenStorage);
```

See `examples/ConfigProvider.php` and `examples/TokenStorage.php` for complete templates.

## [[0.4.0]](https://github.com/OluTech/fortis-pay-common/releases/tag/0.4.0)

### Improved

- Improve error handling when creating Tokenised Transactions.

## [[0.3.0]](https://github.com/OluTech/fortis-pay-common/releases/tag/0.3.0)

### Fixed

- Add missing constants used in generating iFrame.

## [[0.2.0]](https://github.com/OluTech/fortis-pay-common/releases/tag/0.2.0)

### Added

- Refund method now supports a description parameter.
- `refundAch` method.
- `void` method.
- `completeAuthTransaction` method.

## [[0.1.0]](https://github.com/OluTech/fortis-pay-common/releases/tag/0.1.0)

### Added

- Initial release.
