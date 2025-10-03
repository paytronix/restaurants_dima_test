# Ordering Module - API Implementation Plan

## Overview
This document outlines the API-only ordering module implementation for the Laravel X1 project. The module provides JSON HTTP APIs for cart management, checkout, order lifecycle, and payment processing with clear separation of concerns and explicit error handling.

## API Standards

### Versioning
- All API endpoints are versioned under `/api/v1`
- Routes follow RESTful conventions where applicable
- Future versions can be added as `/api/v2` etc.

### Request/Response Format
- **Content Type**: `application/json`
- **Naming Convention**: snake_case for all JSON keys
- **Success Response Format**:
```json
{
  "data": {
    // resource or collection data
  },
  "meta": {
    // optional metadata (pagination, etc.)
  }
}
```

### Error Handling (RFC 7807-inspired)
All error responses follow a consistent structure:
```json
{
  "title": "Error Title",
  "detail": "Detailed error message",
  "status": 422,
  "errors": {
    "field_name": ["validation error message"]
  },
  "trace_id": "unique-request-id"
}
```

**HTTP Status Codes**:
- `200 OK` - Successful GET/PATCH/DELETE
- `201 Created` - Successful POST creating a resource
- `400 Bad Request` - Invalid request format
- `401 Unauthorized` - Authentication required
- `403 Forbidden` - Insufficient permissions
- `404 Not Found` - Resource not found
- `422 Unprocessable Entity` - Validation errors
- `500 Internal Server Error` - Server errors

### Idempotency
- Payment operations require an `Idempotency-Key` header
- The same idempotency key will return the same result
- Keys are valid for 24 hours
- Format: UUID v4 or similar unique identifier

### Pagination
- Query parameters: `page` (default: 1), `per_page` (default: 15, max: 100)
- Response includes meta pagination info:
```json
{
  "data": [...],
  "meta": {
    "current_page": 1,
    "per_page": 15,
    "total": 100,
    "last_page": 7
  }
}
```

### Sorting & Filtering
- Sort: `?sort=field_name` or `?sort=-field_name` (descending)
- Filter: `?filter[field_name]=value`

## Domain Model

### Entities

#### MenuItem
- Represents items available for ordering
- Fields: id, name, description, price (decimal), category, available (boolean), timestamps
- Used as the product catalog

#### User (Customer)
- Reuses existing Laravel User model
- Represents customers placing orders
- Authentication required for checkout and order operations

#### Cart
- Temporary shopping cart for a user session
- Fields: id, user_id (nullable for guest carts), session_id, timestamps
- One active cart per user/session
- Soft deletes when converted to order

#### CartItem
- Individual items in a cart
- Fields: id, cart_id, menu_item_id, quantity, price_snapshot (decimal), special_instructions (text), timestamps
- Foreign keys with cascading deletes
- Price snapshot preserves price at time of adding to cart

#### Order
- Confirmed order after checkout
- Fields: id, user_id, order_number (unique), status (enum), subtotal, tax, total, customer_name, customer_email, customer_phone, pickup_time, delivery_address (nullable), special_instructions, timestamps
- Indexed on order_number, user_id, status
- Uses OrderStatus enum

#### OrderItem
- Line items in an order
- Fields: id, order_id, menu_item_id, quantity, price_snapshot (decimal), special_instructions, timestamps
- Foreign keys with cascading deletes
- Immutable after order creation

#### PaymentAttempt
- Tracks payment processing attempts
- Fields: id, order_id, idempotency_key (unique), amount (decimal), status (enum: pending, success, failed), provider_reference, error_message, timestamps
- Indexed on idempotency_key, order_id
- Used for idempotency checks

### Enums

#### OrderStatus (PHP 8.2 backed enum)
```php
enum OrderStatus: string
{
    case PENDING = 'pending';
    case PAID = 'paid';
    case IN_PREP = 'in_prep';
    case READY = 'ready';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';
    case FAILED = 'failed';
}
```

**State Transitions**:
- PENDING → PAID (after successful payment)
- PAID → IN_PREP (kitchen starts preparation)
- IN_PREP → READY (order ready for pickup/delivery)
- READY → COMPLETED (customer received order)
- PENDING/PAID → CANCELLED (manual cancellation)
- PENDING → FAILED (payment or validation failure)

## API Endpoints

### Cart Management

#### Add Item to Cart
```
POST /api/v1/cart/items
```
**Request Body**:
```json
{
  "menu_item_id": 1,
  "quantity": 2,
  "special_instructions": "No onions"
}
```
**Response**: 201 Created with cart item data

#### Update Cart Item
```
PATCH /api/v1/cart/items/{id}
```
**Request Body**:
```json
{
  "quantity": 3,
  "special_instructions": "Extra sauce"
}
```
**Response**: 200 OK with updated cart item

#### Remove Cart Item
```
DELETE /api/v1/cart/items/{id}
```
**Response**: 200 OK with success message

#### View Cart
```
GET /api/v1/cart
```
**Response**: 200 OK with cart and items data

### Checkout & Orders

#### Checkout (Create Order)
```
POST /api/v1/checkout
```
**Request Body**:
```json
{
  "customer_name": "John Doe",
  "customer_email": "john@example.com",
  "customer_phone": "555-0100",
  "pickup_time": "2024-10-03T18:00:00Z",
  "delivery_address": "123 Main St, Apt 4",
  "special_instructions": "Ring doorbell twice"
}
```
**Response**: 201 Created with order in PENDING status

#### Process Payment
```
POST /api/v1/orders/{order}/pay
Headers:
  Idempotency-Key: uuid-v4-string
```
**Request Body**:
```json
{
  "payment_method": "stub",
  "amount": 25.99
}
```
**Response**: 200 OK with payment attempt and order status updated to PAID

#### Get Order Details
```
GET /api/v1/orders/{order}
```
**Response**: 200 OK with order details including items and payment attempts

#### List User Orders
```
GET /api/v1/orders?page=1&per_page=15
```
**Response**: 200 OK with paginated orders list

#### Update Order Status (Internal/Admin)
```
PATCH /api/v1/orders/{order}/status
```
**Request Body**:
```json
{
  "status": "in_prep"
}
```
**Response**: 200 OK with updated order

## Service Layer

### CartService
**Responsibilities**:
- Manage cart lifecycle (create, retrieve, clear)
- Add/update/remove items with validation
- Calculate cart totals
- Handle guest vs authenticated carts
- Convert cart to order data

**Key Methods**:
- `getOrCreateCart(User|null $user, string|null $sessionId): Cart`
- `addItem(Cart $cart, int $menuItemId, int $quantity, string|null $instructions): CartItem`
- `updateItem(CartItem $item, array $data): CartItem`
- `removeItem(CartItem $item): void`
- `calculateTotal(Cart $cart): array` (returns subtotal, tax, total)
- `clearCart(Cart $cart): void`

### CheckoutService
**Responsibilities**:
- Validate checkout data
- Create order from cart
- Generate order number
- Clear cart after successful checkout
- Handle tax calculations

**Key Methods**:
- `createOrder(Cart $cart, array $customerData): Order`
- `generateOrderNumber(): string`
- `validateCheckoutData(array $data): void`

### OrderService
**Responsibilities**:
- Retrieve orders with filtering/pagination
- Update order status with validation
- Track status transitions
- Send notifications (event dispatch)

**Key Methods**:
- `getOrder(int $orderId, User|null $user): Order`
- `getUserOrders(User $user, array $filters): LengthAwarePaginator`
- `updateStatus(Order $order, OrderStatus $newStatus): Order`
- `validateStatusTransition(OrderStatus $from, OrderStatus $to): bool`

### PaymentServiceStub
**Responsibilities**:
- Process payment attempts (stub implementation)
- Handle idempotency
- Record payment attempts
- Update order status on success

**Interface for future adapters**:
```php
interface PaymentProviderInterface
{
    public function processPayment(Order $order, string $idempotencyKey, array $paymentData): PaymentAttempt;
    public function refundPayment(PaymentAttempt $payment): PaymentAttempt;
}
```

**Key Methods**:
- `processPayment(Order $order, string $idempotencyKey, array $data): PaymentAttempt`
- `checkIdempotency(string $key): ?PaymentAttempt`
- `recordAttempt(Order $order, string $key, array $data): PaymentAttempt`

## Events (Stubs)

### OrderCreated
- Dispatched when order is created from checkout
- Payload: Order instance
- Potential listeners: send confirmation email, update inventory

### OrderPaid
- Dispatched when payment is successful
- Payload: Order instance, PaymentAttempt instance
- Potential listeners: send receipt, notify kitchen

### OrderCancelled
- Dispatched when order is cancelled
- Payload: Order instance, cancellation reason
- Potential listeners: process refund, send cancellation email

### OrderStatusChanged
- Dispatched on any status transition
- Payload: Order instance, old status, new status
- Potential listeners: customer notifications, tracking updates

## Validation & Security

### Form Requests
Each endpoint has a dedicated Form Request class:
- `AddCartItemRequest` - validates menu_item_id, quantity, special_instructions
- `UpdateCartItemRequest` - validates quantity, special_instructions
- `CheckoutRequest` - validates customer data, pickup/delivery info
- `ProcessPaymentRequest` - validates payment data, idempotency key
- `UpdateOrderStatusRequest` - validates status transitions

**Validation Rules**:
- Explicit null checks and type validation
- Custom rules for status transitions
- Menu item availability checks
- Quantity min/max validation (1-99)
- Price validation (must match current menu item price)

### Authorization
- Cart operations: optional authentication (guests allowed)
- Checkout: optional authentication (captures customer data)
- Order viewing: must be order owner or admin
- Order status updates: admin only
- Payment processing: order owner only

## Database Schema

### Indexes
- `menu_items`: `available`, `category`
- `carts`: `user_id`, `session_id`, `created_at`
- `cart_items`: `cart_id`, `menu_item_id`
- `orders`: `order_number` (unique), `user_id`, `status`, `created_at`
- `order_items`: `order_id`, `menu_item_id`
- `payment_attempts`: `idempotency_key` (unique), `order_id`, `status`

### Foreign Keys
- All with appropriate cascading (CASCADE on delete where applicable)
- Soft deletes on `carts` and `orders` for audit trail

### Decimal Precision
- All money fields use `decimal(10, 2)` for precision
- Calculations done with BC Math to avoid floating point issues

## Testing Strategy

### Feature Tests (PHPUnit)
**Cart Operations**:
- Add item to cart (guest and authenticated)
- Update cart item quantity and instructions
- Remove item from cart
- View cart with calculated totals
- Clear cart

**Checkout Flow**:
- Create order from cart (valid data)
- Validation errors for incomplete data
- Cart is cleared after successful checkout
- Order number is unique and generated correctly

**Order Management**:
- View order details (owner only)
- List user orders with pagination
- Filter orders by status
- Unauthorized access attempts fail

**Payment Processing**:
- Process payment with valid idempotency key
- Duplicate idempotency key returns same result
- Payment updates order status to PAID
- Missing idempotency key returns error
- Invalid order status for payment

**Status Transitions**:
- Valid transitions succeed and dispatch events
- Invalid transitions return validation error
- Status history is tracked

### Test Data
- Use factories for all models
- Seed test database with sample menu items
- Create test users with different roles
- Fast tests using in-memory SQLite

### Assertions
- JSON structure matches expected format
- Database state changes correctly
- Events are dispatched
- Status codes are correct
- Error messages are clear

## Configuration & Setup

### Environment Variables (.env.example)
```env
# Ordering Module
ORDERS_TAX_RATE=0.08
ORDERS_MIN_AMOUNT=5.00
ORDERS_MAX_CART_ITEMS=50

# Payment Stub
PAYMENT_STUB_PROVIDER=test
PAYMENT_STUB_API_KEY=stub_key_123
PAYMENT_STUB_SUCCESS_RATE=1.0

# Delivery (future)
DELIVERY_PROVIDER=stub
DELIVERY_API_KEY=stub_delivery_key
```

### Seeders
**MenuItemSeeder**:
- 20-30 sample menu items across categories
- Mix of available and unavailable items
- Realistic prices ($5-$25 range)
- Various categories: appetizers, entrees, desserts, beverages

### Composer Scripts
```json
"scripts": {
    "orders:setup": [
        "@php artisan migrate",
        "@php artisan db:seed --class=MenuItemSeeder"
    ],
    "orders:test": [
        "@php artisan test --testsuite=Feature --filter=Order"
    ],
    "openapi:lint": [
        "spectral lint docs/openapi/orders.yaml"
    ]
}
```

## Code Quality

### PSR-12 Compliance
- Use Laravel Pint for automatic formatting
- Run before each commit

### Naming Conventions
- Controllers: `{Resource}Controller` (e.g., `CartController`)
- Services: `{Domain}Service` (e.g., `CartService`)
- Requests: `{Action}{Resource}Request` (e.g., `AddCartItemRequest`)
- Models: Singular noun (e.g., `Order`, `CartItem`)
- Events: Past tense (e.g., `OrderCreated`, `OrderPaid`)

### Comments
- No inline or explanatory comments unless copying existing code
- PHPDoc blocks for public methods with complex signatures
- Clear, self-documenting code through naming

### Error Handling
- Explicit null checks: `if ($value === null) { ... }`
- Type hints on all method parameters and return types
- Guard clauses at method start
- Explicit else branches where logic requires

## Implementation Checklist

### Phase 1: Foundation
- [x] Create documentation directory structure
- [x] Write this plan document
- [ ] Configure API routes in bootstrap/app.php
- [ ] Create base API controller with error handling
- [ ] Create RFC 7807 error response helper

### Phase 2: Database
- [ ] Create OrderStatus enum
- [ ] Create MenuItem migration and model
- [ ] Create Cart migration and model
- [ ] Create CartItem migration and model
- [ ] Create Order migration and model
- [ ] Create OrderItem migration and model
- [ ] Create PaymentAttempt migration and model
- [ ] Define model relationships
- [ ] Create MenuItemSeeder

### Phase 3: Cart Module
- [ ] Create CartService
- [ ] Create AddCartItemRequest
- [ ] Create UpdateCartItemRequest
- [ ] Create CartController with routes
- [ ] Write cart feature tests

### Phase 4: Checkout Module
- [ ] Create CheckoutService
- [ ] Create CheckoutRequest
- [ ] Create CheckoutController
- [ ] Write checkout feature tests

### Phase 5: Order Module
- [ ] Create OrderService
- [ ] Create UpdateOrderStatusRequest
- [ ] Create OrderController with routes
- [ ] Write order feature tests

### Phase 6: Payment Module
- [ ] Create PaymentProviderInterface
- [ ] Create PaymentServiceStub
- [ ] Create ProcessPaymentRequest
- [ ] Add payment endpoint to OrderController
- [ ] Write payment feature tests (including idempotency)

### Phase 7: Events
- [ ] Create OrderCreated event
- [ ] Create OrderPaid event
- [ ] Create OrderCancelled event
- [ ] Create OrderStatusChanged event
- [ ] Update services to dispatch events

### Phase 8: Documentation
- [ ] Create OpenAPI specification (orders.yaml)
- [ ] Create README_ORDERS.md with setup instructions
- [ ] Update .env.example with stub keys
- [ ] Add example requests/responses to docs

### Phase 9: Testing & Quality
- [ ] Run all feature tests
- [ ] Run Laravel Pint for formatting
- [ ] Verify all routes register correctly
- [ ] Test app boots without errors
- [ ] Test migrations and seeders

### Phase 10: Delivery
- [ ] Commit changes with clear messages
- [ ] Create pull request with checklist
- [ ] Wait for CI to pass
- [ ] Address any CI failures

## Non-Goals (Out of Scope)

- Real payment gateway integrations (Stripe, PayPal, etc.)
- Real delivery service integrations
- Admin dashboard or UI
- Background job processing (unless needed for events)
- Advanced inventory management
- Customer authentication system (uses existing User)
- Email/SMS notification implementations
- Advanced analytics or reporting
- Multi-currency support
- Discount/coupon system
- Advanced tax calculations

## Future Enhancements

These are explicitly out of scope but documented for future reference:
- Real payment provider adapters
- Delivery tracking integration
- Order history and analytics
- Loyalty points system
- Scheduled orders
- Subscription/recurring orders
- Multi-location support
- Kitchen display system integration
