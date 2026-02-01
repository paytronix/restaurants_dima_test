# Module 5: Payments Adapters - Design Document

## Overview

This document describes the architecture and implementation plan for the Payments module, which provides a clean abstraction layer for multiple payment providers (Stripe, Przelewy24), webhook handling with signature verification and replay protection, idempotency support, and reconciliation capabilities.

## Provider Abstraction

### Selection Mechanism

Payment providers are selected via configuration. The default provider is set in the environment variable `PAYMENT_PROVIDER_DEFAULT`. When creating a payment, the client can optionally specify a provider; otherwise, the default is used.

Supported providers:
- `stub` - Local/dev testing provider (always succeeds)
- `stripe` - Stripe payment gateway
- `p24` - Przelewy24 payment gateway (Polish market)

Provider configuration is stored in `config/payments.php` and reads from environment variables.

### Provider Interface

All providers implement `PaymentProviderInterface`:

```php
interface PaymentProviderInterface
{
    public function getName(): string;
    public function createPayment(Order $order, int $amount, string $currency, string $idempotencyKey, array $context = []): PaymentResult;
    public function confirmPayment(PaymentTransaction $transaction, array $context = []): PaymentResult;
    public function fetchPaymentStatus(PaymentTransaction $transaction): PaymentStatusResult;
    public function verifyWebhook(Request $request): WebhookVerificationResult;
    public function parseWebhook(Request $request): WebhookEventDTO;
}
```

### Result Objects

- `PaymentResult` - Contains success status, provider payment ID, checkout URL (if applicable), client secret (for Stripe), and error details
- `PaymentStatusResult` - Contains current status from provider, raw response data
- `WebhookVerificationResult` - Contains validity flag and error message if invalid
- `WebhookEventDTO` - Normalized webhook event data (event type, payment ID, status, raw payload)

## Payment Lifecycle and State Machine

### Transaction States

```
pending -> processing -> succeeded
                     -> failed
                     -> cancelled
pending -> expired (after timeout)
```

State transitions:
- `pending`: Initial state when payment is created
- `processing`: Payment is being processed by provider
- `succeeded`: Payment completed successfully
- `failed`: Payment failed (can be retried with new transaction)
- `cancelled`: Payment was cancelled by user or system
- `expired`: Payment was not completed within timeout period

### State Transition Rules

1. Only `pending` transactions can transition to `processing`
2. Only `processing` transactions can transition to `succeeded`, `failed`, or `cancelled`
3. `succeeded`, `failed`, `cancelled`, and `expired` are terminal states
4. Webhooks can only advance state forward, never backward
5. Reconciliation can update state based on provider's authoritative status

## Webhook Flow and Security Model

### Webhook Processing Flow

1. Receive webhook at `/api/v1/webhooks/payments/{provider}`
2. Verify signature using provider-specific method
3. Parse webhook payload into normalized DTO
4. Check for duplicate event (by provider + event_id)
5. Store webhook event in `payment_webhook_events` table
6. Process event idempotently:
   - Find associated transaction
   - Apply state transition if valid
   - Update order status if payment succeeded
7. Mark webhook event as processed

### Signature Verification

**Stripe**: Uses HMAC-SHA256 with webhook secret. The signature is in the `Stripe-Signature` header.

**Przelewy24**: Uses CRC verification with merchant credentials. Signature computed from specific fields.

**Stub**: No signature verification (development only).

### Replay Protection

- Each webhook event is stored with its `event_id` from the provider
- Before processing, check if event_id already exists for this provider
- If duplicate, return 200 OK without reprocessing
- This prevents double-charging or double state transitions

## Idempotency Rules

### Header Requirement

All payment creation and confirmation endpoints require an `Idempotency-Key` header. This key must be:
- A string between 1-255 characters
- Unique per logical operation
- Client-generated (typically UUID)

### Storage

Idempotency keys are stored with:
- `key_hash`: SHA-256 hash of the raw key (never store raw)
- `scope`: The operation scope (e.g., "payment_create", "payment_confirm")
- `request_hash`: SHA-256 hash of the request payload
- `response_json`: The response returned for this key
- `status`: pending, completed, failed
- `expires_at`: Keys expire after 24 hours

### Conflict Behavior

When an idempotency key is reused:
1. If request payload hash matches: Return cached response (safe retry)
2. If request payload hash differs: Return 409 Conflict with error:
   ```json
   {
     "title": "Idempotency Conflict",
     "detail": "This idempotency key has already been used with different request parameters",
     "status": 409
   }
   ```

### Safe Retries

If a request with the same idempotency key and same payload is received:
- Return the exact same response as the original request
- Do not create duplicate transactions
- This enables safe client retries on network failures

## Reconciliation Approach

### Command

```bash
php artisan payments:reconcile --provider=stripe --since="2024-01-01"
```

### Process

1. Query `payment_transactions` for non-terminal states (`pending`, `processing`) where:
   - Provider matches the specified provider
   - Created after the `--since` date
2. For each transaction:
   - Call `fetchPaymentStatus()` on the provider
   - Compare local status with provider status
   - If different, update local status to match provider
   - Log the mismatch for audit
3. Generate summary report

### Limitations

- Only reconciles transactions with a `provider_payment_id`
- Cannot reconcile transactions that never reached the provider
- Rate limited to avoid provider API throttling
- Should be run during low-traffic periods

### Idempotency

Running reconciliation multiple times produces the same result:
- Already-terminal transactions are skipped
- Status updates are based on provider's authoritative state
- No side effects beyond status updates and logging

## Data Model

### Orders Table (Minimal)

Since Module 1 (Orders) doesn't exist yet, we create a minimal orders table:

```sql
CREATE TABLE orders (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NULL,
    location_id BIGINT UNSIGNED NULL,
    status VARCHAR(50) DEFAULT 'draft',
    subtotal INT DEFAULT 0,
    total INT DEFAULT 0,
    currency VARCHAR(3) DEFAULT 'PLN',
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (location_id) REFERENCES locations(id)
);
```

### Payment Transactions Table

```sql
CREATE TABLE payment_transactions (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    order_id BIGINT UNSIGNED NOT NULL,
    provider VARCHAR(50) NOT NULL,
    provider_payment_id VARCHAR(255) NULL,
    status VARCHAR(50) DEFAULT 'pending',
    amount INT NOT NULL,
    currency VARCHAR(3) NOT NULL,
    idempotency_key_hash VARCHAR(64) NOT NULL,
    checkout_url TEXT NULL,
    client_secret VARCHAR(255) NULL,
    metadata_json JSON NULL,
    error_message TEXT NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id),
    INDEX idx_provider_payment_id (provider, provider_payment_id),
    INDEX idx_idempotency_key (idempotency_key_hash),
    INDEX idx_status (status)
);
```

### Payment Webhook Events Table

```sql
CREATE TABLE payment_webhook_events (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    provider VARCHAR(50) NOT NULL,
    event_id VARCHAR(255) NOT NULL,
    event_type VARCHAR(100) NULL,
    signature_valid BOOLEAN DEFAULT FALSE,
    payload_json JSON NOT NULL,
    received_at TIMESTAMP NOT NULL,
    processed_at TIMESTAMP NULL,
    processing_error TEXT NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    UNIQUE INDEX idx_provider_event (provider, event_id),
    INDEX idx_processed (processed_at)
);
```

### Idempotency Keys Table

```sql
CREATE TABLE idempotency_keys (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    key_hash VARCHAR(64) NOT NULL,
    scope VARCHAR(100) NOT NULL,
    request_hash VARCHAR(64) NOT NULL,
    response_json JSON NULL,
    status VARCHAR(50) DEFAULT 'pending',
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    UNIQUE INDEX idx_key_scope (key_hash, scope),
    INDEX idx_expires (expires_at)
);
```

## API Endpoints

### Create Payment

```
POST /api/v1/orders/{order}/payments
Headers:
  Authorization: Bearer {token}
  Idempotency-Key: {uuid}
Body:
  {
    "provider": "stripe" // optional, defaults to PAYMENT_PROVIDER_DEFAULT
  }
Response 201:
  {
    "data": {
      "id": 1,
      "order_id": 1,
      "provider": "stripe",
      "status": "pending",
      "amount": 5000,
      "currency": "PLN",
      "checkout_url": "https://checkout.stripe.com/...",
      "client_secret": "pi_xxx_secret_xxx"
    }
  }
```

### Get Payment Status

```
GET /api/v1/orders/{order}/payments/{payment}
Headers:
  Authorization: Bearer {token}
Response 200:
  {
    "data": {
      "id": 1,
      "order_id": 1,
      "provider": "stripe",
      "status": "succeeded",
      "amount": 5000,
      "currency": "PLN"
    }
  }
```

### Webhook Handler

```
POST /api/v1/webhooks/payments/{provider}
Headers:
  Stripe-Signature: {signature} // for Stripe
Body:
  {provider-specific webhook payload}
Response 200:
  {"received": true}
```

### Legacy Pay Endpoint (Backward Compatibility)

```
POST /api/v1/orders/{order}/pay
Headers:
  Authorization: Bearer {token}
  Idempotency-Key: {uuid}
Body:
  {
    "amount": 5000 // optional, defaults to order total
  }
Response 200:
  {
    "data": {
      "id": 1,
      "status": "succeeded",
      "message": "Payment processed successfully"
    }
  }
```

This endpoint uses the `StubPaymentProvider` by default for backward compatibility, immediately marking the payment as succeeded.

## Configuration

Environment variables:

```env
# Payment Module Configuration
PAYMENT_PROVIDER_DEFAULT=stub

# Stripe Configuration
STRIPE_SECRET=sk_test_...
STRIPE_WEBHOOK_SECRET=whsec_...

# Przelewy24 Configuration
P24_MERCHANT_ID=
P24_CRC=
P24_API_KEY=
P24_SANDBOX=true
```

## Design Decisions

1. **Minimal Orders Table**: Since Module 1 doesn't exist, we create a minimal orders table. This can be extended later when the full Orders module is implemented.

2. **Hash-Only Storage for Idempotency Keys**: Raw idempotency keys are never stored to prevent potential information leakage.

3. **Webhook Event Storage**: All webhook payloads are stored for audit and debugging purposes. Sensitive data should be redacted before storage in production.

4. **Provider Selection**: Defaults to `stub` for development safety. Production should explicitly set the provider.

5. **State Machine Enforcement**: State transitions are enforced in the PaymentTransaction model to prevent invalid states.

6. **Reconciliation as Command**: Reconciliation is implemented as an Artisan command rather than a scheduled job, giving operators control over when it runs.
