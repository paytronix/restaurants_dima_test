# Module 6: Promotions & Coupons - Design Document

## Overview

This document describes the design and implementation plan for the Promotions & Coupons module (Module 6) in the X1 restaurant ordering API. The module provides API-only functionality for managing promotional coupons with discount capabilities, eligibility rules, and anti-abuse protections.

## Scope

### In Scope

- Coupon/promo code management (CRUD operations)
- Discount types: percentage and fixed amount
- Eligibility rules: minimum cart subtotal, category/item targeting, date/time windows
- Usage limits: global limits and per-customer limits
- Anti-abuse: rate limiting for invalid attempts, IP/device throttling
- Idempotent apply/remove operations
- Integration with order totals and checkout flow
- Reservation-based redemption flow

### Out of Scope

- UI/frontend components
- Marketing campaign dashboards
- Complex stacking of multiple promotions (single coupon per order for now)
- Loyalty points, referrals, gift cards (future modules)

## Data Model

### Coupons Table

The `coupons` table stores coupon definitions with their discount rules and constraints.

```
coupons
├── id (bigint, PK)
├── code (varchar, unique, uppercase normalized)
├── name (varchar, nullable) - internal/display name
├── discount_type (enum: 'percent', 'fixed')
├── discount_value (decimal 12,2) - percent as whole number (e.g., 10.00 = 10%), fixed as amount in minor units
├── currency (char 3, nullable) - required for fixed discounts
├── starts_at (timestamp, nullable) - coupon valid from
├── ends_at (timestamp, nullable) - coupon valid until
├── min_subtotal (integer, default 0) - minimum order subtotal in minor units (cents)
├── max_uses_total (integer, nullable) - global usage limit
├── max_uses_per_customer (integer, nullable) - per-user usage limit
├── is_active (boolean, default true)
├── deleted_at (timestamp, nullable) - soft delete
├── created_at (timestamp)
├── updated_at (timestamp)
```

Indexes:
- `code` (unique)
- `is_active, starts_at, ends_at` (for active coupon queries)

### Coupon Redemptions Table

The `coupon_redemptions` table tracks coupon usage with a reservation-based flow.

```
coupon_redemptions
├── id (bigint, PK)
├── coupon_id (bigint, FK -> coupons.id)
├── user_id (bigint, nullable, FK -> users.id)
├── order_id (bigint, nullable, FK -> orders.id)
├── status (enum: 'reserved', 'redeemed', 'released', 'expired')
├── reserved_at (timestamp)
├── redeemed_at (timestamp, nullable)
├── expires_at (timestamp, nullable) - reservation expiry
├── ip_hash (varchar 64, nullable) - SHA256 of IP for anti-abuse
├── user_agent_hash (varchar 64, nullable) - SHA256 of user agent
├── created_at (timestamp)
├── updated_at (timestamp)
```

Indexes:
- `(coupon_id, status)` - for counting active redemptions
- `(user_id, coupon_id, status)` - for per-customer limit checks
- `(order_id)` - for order lookup

### Coupon Targets Table

The `coupon_targets` table defines which categories or menu items a coupon applies to.

```
coupon_targets
├── id (bigint, PK)
├── coupon_id (bigint, FK -> coupons.id)
├── target_type (enum: 'category', 'menu_item')
├── target_id (bigint) - references categories.id or menu_items.id
├── created_at (timestamp)
├── updated_at (timestamp)
```

Indexes:
- `(coupon_id, target_type, target_id)` (unique)

### Order Extensions

The existing `orders` table will be extended with coupon-related fields:

```
orders (additions)
├── coupon_id (bigint, nullable, FK -> coupons.id)
├── coupon_code (varchar, nullable) - snapshot of code at time of order
├── discount_total (integer, default 0) - discount amount in minor units
```

Note: The system stores amounts as integers (minor currency units/cents). The `subtotal` represents the sum of line items before discount, `discount_total` is the discount amount, and `total` = `subtotal` - `discount_total` + any fees.

## Discount Computation Rules

### Discount Types

1. **Percentage Discount**: `discount_value` represents the percentage (e.g., 10.00 = 10%). Applied to eligible items' subtotal.

2. **Fixed Amount Discount**: `discount_value` represents the amount in minor units (cents). Requires matching `currency`.

### Computation Flow

1. Calculate eligible subtotal (items matching coupon targets, or all items if no targets)
2. Apply discount based on type:
   - Percent: `eligible_subtotal * (discount_value / 100)`
   - Fixed: `min(discount_value, eligible_subtotal)` (cannot exceed eligible amount)
3. Round to nearest minor unit (standard rounding)
4. Discount cannot exceed order subtotal
5. Final total = subtotal - discount_total

### Eligibility Evaluation Order

When validating a coupon, checks are performed in this order (fail-fast):

1. **Existence**: Coupon code exists and is not soft-deleted
2. **Active Flag**: `is_active = true`
3. **Date Window**: Current time is within `starts_at` and `ends_at` (if set)
4. **Currency Match**: For fixed discounts, order currency must match coupon currency
5. **Minimum Subtotal**: Order subtotal >= `min_subtotal`
6. **Global Usage Limit**: Count of `redeemed` status < `max_uses_total` (if set)
7. **Per-Customer Limit**: Count of `redeemed` for user < `max_uses_per_customer` (if set)
8. **Target Eligibility**: If targets exist, at least one order item must match

Note: Checks 1-4 are "gate controls" that determine if the coupon can be used at all. Checks 5-8 are "qualification controls" that depend on the specific order context.

## Single Coupon Model

The system enforces a single active coupon per order:

- Applying a new coupon replaces any previously applied coupon
- The previous coupon's reservation (if any) is released
- This simplifies discount calculation and avoids stacking complexity
- Future extension: Add `allow_stacking` flag and stacking rules table

## Reservation Flow

The reservation system prevents race conditions and ensures accurate usage counting:

### Apply Coupon to Order (Draft)

1. Validate coupon eligibility
2. If order has existing coupon, release its reservation
3. Create reservation with status `reserved`, set `expires_at` (TTL from config)
4. Update order with `coupon_id`, `coupon_code`, recalculated `discount_total` and `total`

### Checkout/Order Finalization

1. Verify reservation exists and is not expired
2. Transition reservation status to `redeemed`, set `redeemed_at`
3. Snapshot coupon data in order (already done at apply time)

### Reservation Expiry

1. Background job or lazy check on access
2. Expired reservations transition to `expired` status
3. Order's coupon fields are cleared if reservation expired

### Order Cancellation

1. If order has active reservation, transition to `released`
2. Released redemptions don't count toward limits

## Anti-Abuse Measures

### Rate Limiting Invalid Attempts

- Track failed coupon validation attempts per IP hash and user
- After N failures within window, return 429 Too Many Requests
- Configuration: `COUPON_INVALID_ATTEMPT_LIMIT=5`, `COUPON_INVALID_ATTEMPT_WINDOW=60` (seconds)

### Generic Error Messages

- User-facing errors do not reveal whether a coupon exists, is expired, or overused
- Generic message: "This coupon code is not valid"
- Detailed reason logged server-side for debugging

### IP/Device Tracking

- Store hashed IP and user agent on redemptions
- Enables detection of abuse patterns (future enhancement)

## API Endpoints

### Public/Auth-Protected Endpoints

#### Apply Coupon to Order

```
POST /api/v1/orders/{order}/coupon
Authorization: Bearer {token}
Content-Type: application/json

Request:
{
  "code": "WELCOME10"
}

Response 200:
{
  "data": {
    "order": {
      "id": 123,
      "subtotal": 5000,
      "discount_total": 500,
      "total": 4500,
      "currency": "PLN",
      "coupon": {
        "code": "WELCOME10",
        "discount_type": "percent",
        "discount_value": "10.00"
      }
    }
  },
  "meta": {}
}

Response 422 (invalid coupon):
{
  "title": "Validation Error",
  "detail": "This coupon code is not valid",
  "status": 422,
  "errors": {
    "code": ["This coupon code is not valid"]
  },
  "trace_id": "abc123"
}

Response 429 (rate limited):
{
  "title": "Too Many Requests",
  "detail": "Too many invalid coupon attempts. Please try again later.",
  "status": 429,
  "trace_id": "abc123"
}
```

#### Remove Coupon from Order

```
DELETE /api/v1/orders/{order}/coupon
Authorization: Bearer {token}

Response 200:
{
  "data": {
    "order": {
      "id": 123,
      "subtotal": 5000,
      "discount_total": 0,
      "total": 5000,
      "currency": "PLN",
      "coupon": null
    }
  },
  "meta": {}
}
```

This endpoint is idempotent - removing a coupon when none is applied returns success.

#### Get Order (includes coupon info)

```
GET /api/v1/orders/{order}
Authorization: Bearer {token}

Response 200:
{
  "data": {
    "id": 123,
    "status": "draft",
    "subtotal": 5000,
    "discount_total": 500,
    "total": 4500,
    "currency": "PLN",
    "coupon": {
      "code": "WELCOME10",
      "discount_type": "percent",
      "discount_value": "10.00"
    }
  },
  "meta": {}
}
```

### Admin Endpoints

All admin endpoints require Bearer authentication.

#### Create Coupon

```
POST /api/v1/admin/coupons
Authorization: Bearer {token}
Content-Type: application/json

Request:
{
  "code": "SUMMER20",
  "name": "Summer Sale 20%",
  "discount_type": "percent",
  "discount_value": "20.00",
  "starts_at": "2026-06-01T00:00:00Z",
  "ends_at": "2026-08-31T23:59:59Z",
  "min_subtotal": 5000,
  "max_uses_total": 1000,
  "max_uses_per_customer": 1,
  "is_active": true
}

Response 201:
{
  "data": {
    "id": 1,
    "code": "SUMMER20",
    "name": "Summer Sale 20%",
    "discount_type": "percent",
    "discount_value": "20.00",
    "currency": null,
    "starts_at": "2026-06-01T00:00:00Z",
    "ends_at": "2026-08-31T23:59:59Z",
    "min_subtotal": 5000,
    "max_uses_total": 1000,
    "max_uses_per_customer": 1,
    "is_active": true,
    "created_at": "2026-02-02T10:00:00Z",
    "updated_at": "2026-02-02T10:00:00Z"
  },
  "meta": {}
}
```

#### Update Coupon

```
PATCH /api/v1/admin/coupons/{id}
Authorization: Bearer {token}
Content-Type: application/json

Request:
{
  "is_active": false
}

Response 200:
{
  "data": { ... updated coupon ... },
  "meta": {}
}
```

#### List Coupons

```
GET /api/v1/admin/coupons?active=true&code=SUMMER&page=1
Authorization: Bearer {token}

Response 200:
{
  "data": [ ... array of coupons ... ],
  "meta": {
    "current_page": 1,
    "per_page": 15,
    "total": 50
  }
}
```

#### Delete Coupon (Soft Delete)

```
DELETE /api/v1/admin/coupons/{id}
Authorization: Bearer {token}

Response 204 No Content
```

#### Attach Targets to Coupon

```
POST /api/v1/admin/coupons/{id}/targets
Authorization: Bearer {token}
Content-Type: application/json

Request:
{
  "target_type": "category",
  "target_id": 5
}

Response 201:
{
  "data": {
    "id": 1,
    "coupon_id": 1,
    "target_type": "category",
    "target_id": 5
  },
  "meta": {}
}
```

#### Remove Target from Coupon

```
DELETE /api/v1/admin/coupons/{id}/targets/{target_id}
Authorization: Bearer {token}

Response 204 No Content
```

## Error Handling

All errors follow RFC7807 format:

```json
{
  "title": "Error Title",
  "detail": "Human-readable description",
  "status": 422,
  "errors": {
    "field": ["Validation message"]
  },
  "trace_id": "unique-trace-id"
}
```

### Status Codes

| Code | Scenario |
|------|----------|
| 200 | Success |
| 201 | Resource created |
| 204 | Resource deleted |
| 400 | Bad request (malformed JSON, missing required fields) |
| 401 | Unauthorized (missing/invalid token) |
| 403 | Forbidden (insufficient permissions) |
| 404 | Resource not found |
| 409 | Conflict (reservation collision, concurrent modification) |
| 422 | Validation error (invalid/expired/ineligible coupon) |
| 429 | Rate limited (too many invalid attempts) |
| 500 | Internal server error |

## Integration Points

### Order Totals

When a coupon is applied or removed, the order's totals must be recalculated:

```
order.discount_total = calculated_discount
order.total = order.subtotal - order.discount_total
```

### Checkout Flow

The existing checkout/payment flow must:

1. Verify coupon reservation is still valid (not expired)
2. Transition reservation to `redeemed` on successful order completion
3. Include coupon data in order snapshot for historical reference

### Payment Totals

Payment amount should use `order.total` which already accounts for discounts. No changes needed to payment module if it uses `order.total`.

## Configuration

Environment variables:

```
COUPON_RESERVATION_TTL=900          # Reservation expiry in seconds (15 minutes)
COUPON_INVALID_ATTEMPT_LIMIT=5      # Max invalid attempts before throttle
COUPON_INVALID_ATTEMPT_WINDOW=60    # Throttle window in seconds
```

## Service Architecture

### CouponValidationService

Responsible for validating coupon eligibility:

- `validate(string $code, Order $order, ?User $user): ValidationResult`
- Returns detailed validation result with pass/fail and reason

### DiscountCalculator

Computes discount amounts:

- `calculate(Coupon $coupon, Order $order): DiscountResult`
- Handles both percent and fixed discount types
- Respects target filtering

### PromotionService

Orchestrates coupon operations:

- `applyCoupon(Order $order, string $code, ?User $user): ApplyResult`
- `removeCoupon(Order $order): RemoveResult`
- `redeemReservation(Order $order): bool`
- `releaseReservation(Order $order): bool`

### AntiAbuseService

Handles rate limiting and abuse detection:

- `checkRateLimit(string $ipHash, ?int $userId): bool`
- `recordInvalidAttempt(string $ipHash, ?int $userId): void`
- `clearAttempts(string $ipHash, ?int $userId): void`

## Testing Strategy

### Unit Tests

- CouponValidationService: All validation rules
- DiscountCalculator: Percent and fixed calculations, rounding, edge cases
- AntiAbuseService: Rate limiting logic

### Feature Tests

- Apply valid coupon (percent and fixed)
- Apply coupon with min_subtotal requirement
- Apply coupon with date window constraints
- Apply coupon with category/item targeting
- Per-customer and global usage limits
- Replace coupon behavior
- Remove coupon (idempotent)
- Checkout with coupon (reservation -> redeemed)
- Expired reservation handling
- Rate limiting on invalid attempts
- Admin CRUD operations

## Future Considerations

- Multiple coupon stacking with priority rules
- Automatic coupon application based on cart contents
- Coupon generation (bulk codes with shared rules)
- Analytics and reporting on coupon usage
- A/B testing for promotional effectiveness
