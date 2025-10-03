# Orders Module - API Documentation

## Overview

This is an API-only ordering module for a restaurant management system. The module provides endpoints for cart management, checkout, order lifecycle management, and payment processing (stub implementation).

## Features

- **Cart Management**: Add, update, remove items from cart
- **Checkout**: Create orders from cart with customer information
- **Order Lifecycle**: Track orders through status transitions (PENDING → PAID → IN_PREP → READY → COMPLETED)
- **Payment Processing**: Stub implementation with idempotency support
- **Error Handling**: RFC 7807-inspired error responses with trace IDs

## Setup

### Prerequisites

- PHP 8.2+
- Composer
- SQLite (default) or other database

### Installation

1. Clone the repository:
```bash
git clone https://github.com/paytronix/restaurants_dima_test.git
cd restaurants_dima_test
```

2. Install dependencies:
```bash
composer install
```

3. Set up environment:
```bash
cp .env.example .env
php artisan key:generate
```

4. Run migrations:
```bash
php artisan migrate
```

5. Seed the database with sample menu items:
```bash
php artisan db:seed --class=MenuItemSeeder
```

6. Start the development server:
```bash
php artisan serve
```

The API will be available at `http://localhost:8000/api/v1`

## Configuration

### Environment Variables

Add these to your `.env` file:

```env
# Orders Module Configuration
ORDERS_TAX_RATE=0.08
ORDERS_PAYMENT_STUB_SUCCESS_RATE=1.0
```

- `ORDERS_TAX_RATE`: Tax rate for order calculations (default: 0.08 = 8%)
- `ORDERS_PAYMENT_STUB_SUCCESS_RATE`: Success rate for stub payment processing (0.0 to 1.0, default: 1.0)

## API Endpoints

### Cart Operations

#### Get Cart
```http
GET /api/v1/cart
```

Returns the current cart with items and totals.

#### Add Item to Cart
```http
POST /api/v1/cart/items
Content-Type: application/json

{
  "menu_item_id": 1,
  "quantity": 2,
  "special_instructions": "No onions"
}
```

#### Update Cart Item
```http
PATCH /api/v1/cart/items/{cartItemId}
Content-Type: application/json

{
  "quantity": 3,
  "special_instructions": "Extra cheese"
}
```

#### Remove Cart Item
```http
DELETE /api/v1/cart/items/{cartItemId}
```

### Checkout

#### Create Order from Cart
```http
POST /api/v1/checkout
Content-Type: application/json

{
  "customer_name": "John Doe",
  "customer_email": "john@example.com",
  "customer_phone": "+1-555-1234",
  "pickup_time": "2025-10-03T18:00:00Z",
  "delivery_address": "123 Main St",
  "special_instructions": "Ring doorbell"
}
```

### Order Management

#### List Orders (requires authentication)
```http
GET /api/v1/orders?status=pending&per_page=15
```

Query Parameters:
- `status`: Filter by order status (pending, paid, in_prep, ready, completed, cancelled, failed)
- `sort`: Sort field (default: created_at)
- `per_page`: Items per page (default: 15, max: 100)

#### Get Order Details
```http
GET /api/v1/orders/{orderId}
```

#### Update Order Status
```http
PATCH /api/v1/orders/{orderId}/status
Content-Type: application/json

{
  "status": "in_prep"
}
```

### Payment Processing

#### Process Payment
```http
POST /api/v1/orders/{orderId}/pay
Content-Type: application/json
Idempotency-Key: 550e8400-e29b-41d4-a716-446655440000

{
  "amount": 28.06
}
```

**Important**: The `Idempotency-Key` header is required to ensure payment requests can be safely retried.

## Order Status Flow

```
PENDING → PAID → IN_PREP → READY → COMPLETED
   ↓        ↓        ↓         ↓
CANCELLED / FAILED
```

### Valid Status Transitions

- **PENDING** → PAID, CANCELLED, FAILED
- **PAID** → IN_PREP, CANCELLED
- **IN_PREP** → READY, CANCELLED
- **READY** → COMPLETED, CANCELLED
- **COMPLETED** → (terminal state)
- **CANCELLED** → (terminal state)
- **FAILED** → (terminal state)

Any status can transition to CANCELLED at any time.

## Response Format

### Success Response
```json
{
  "data": {
    "order": {
      "id": 1,
      "order_number": "ORD-ABC12345",
      "status": "pending",
      "total": 28.06
    }
  },
  "meta": {
    "trace_id": "550e8400-e29b-41d4-a716-446655440000"
  }
}
```

### Error Response (RFC 7807-inspired)
```json
{
  "title": "Validation Error",
  "detail": "qty must be >= 1",
  "status": 422,
  "errors": {
    "qty": ["The qty field must be at least 1."]
  },
  "trace_id": "550e8400-e29b-41d4-a716-446655440000"
}
```

## Testing

### Run Tests
```bash
php artisan test
```

### Run Specific Test Suite
```bash
php artisan test tests/Feature/Orders/CartTest.php
```

### Test Coverage Includes
- Cart operations (add, update, remove, calculate totals)
- Checkout flow (validation, order creation, cart clearing)
- Order management (listing, filtering, pagination)
- Status transitions (valid and invalid transitions)
- Payment idempotency (duplicate requests, key validation)

## Development

### Code Quality

The project follows PSR-12 coding standards and Laravel conventions.

### Domain Model

- **MenuItem**: Restaurant menu items
- **Cart**: Shopping cart (session or user-based)
- **CartItem**: Items in a cart
- **Order**: Customer order
- **OrderItem**: Items in an order (snapshot of cart items)
- **PaymentAttempt**: Payment processing attempts
- **OrderStatus**: Enum for order states

### Services

- **CartService**: Cart operations and calculations
- **CheckoutService**: Order creation from cart
- **OrderService**: Order management and status updates
- **PaymentServiceStub**: Stub payment processing with idempotency

### Events

- **OrderCreated**: Fired when an order is created
- **OrderPaid**: Fired when payment succeeds
- **OrderCancelled**: Fired when an order is cancelled
- **OrderStatusChanged**: Fired on any status transition

## OpenAPI Documentation

Full API specification is available at `/docs/openapi/orders.yaml`

You can view it using tools like:
- Swagger UI
- Postman
- ReDoc

## Troubleshooting

### Database Issues
```bash
# Reset database
php artisan migrate:fresh --seed
```

### Clear Caches
```bash
php artisan config:clear
php artisan cache:clear
php artisan route:clear
```

### View Routes
```bash
php artisan route:list --path=api/v1
```

## Non-Goals

This module explicitly does NOT include:
- UI components (Blade, Inertia, Vue)
- Admin screens
- Real payment gateway integrations
- Real delivery service integrations
- Background job workers
- Full E2E integrations

These are intentionally left as interfaces/stubs for future implementation.

## Contributing

When contributing to this module:
1. Follow PSR-12 and Laravel naming conventions
2. Write feature tests for new functionality
3. Update OpenAPI spec for new endpoints
4. Do not add inline comments unless explicitly needed
5. Keep services small and focused

## License

MIT
