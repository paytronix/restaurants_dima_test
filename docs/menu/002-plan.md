# Module 2: Catalog Menu API - Implementation Plan

## Overview
Module 2 provides a complete API-only solution for managing restaurant menu catalog including categories, menu items, modifiers/options, availability scheduling, and sold-out tracking. The implementation includes a public cacheable catalog endpoint and admin CRUD operations with Bearer token authentication.

## Data Model

### Database Tables (7 tables)

1. **categories**
   - `id`, `name`, `position`, `is_active`, `timestamps`
   - Controls menu organization and display order

2. **menu_items**
   - `id`, `category_id` (FK), `name`, `description`, `price` (decimal 12,2), `currency` (char 3, default PLN), `image_url`, `is_active`, `timestamps`
   - Core menu items with pricing and categorization

3. **modifiers**
   - `id`, `name`, `type` (single|multiple), `min_select`, `max_select`, `is_required`, `timestamps`
   - Modifier groups (e.g., "Size", "Toppings")

4. **modifier_options**
   - `id`, `modifier_id` (FK), `name`, `price_delta` (decimal 12,2), `is_active`, `position`, `timestamps`
   - Individual options within modifiers with price adjustments

5. **menu_item_modifier** (pivot)
   - `menu_item_id` (FK), `modifier_id` (FK)
   - Links menu items to their applicable modifiers

6. **item_availabilities**
   - `id`, `menu_item_id` (FK), `day_of_week` (0-6), `time_from`, `time_to`
   - Time windows when items are available

7. **item_soldouts**
   - `id`, `menu_item_id` (FK), `date`, `reason`
   - Tracks items sold out for specific dates

### Relationships
- Category → Menu Items (1:N)
- Menu Item → Category (N:1)
- Menu Item ↔ Modifiers (N:N via pivot)
- Modifier → Modifier Options (1:N)
- Menu Item → Availabilities (1:N)
- Menu Item → Soldouts (1:N)

## Availability Logic

An item's `is_available_now` flag is calculated as TRUE when ALL conditions are met:

1. `menu_items.is_active = true`
2. Current day/time falls within at least one availability window from `item_availabilities`
3. No `item_soldouts` record exists for today's date

This logic is implemented in `CatalogReadService::isItemAvailableNow()` and applied when generating the public catalog.

## Caching Strategy

### Cache Configuration
- **Cache Key**: `catalog:v1` (base key)
- **Cache Key Variant**: `catalog:v1:inactive` (when `include_inactive=true`)
- **TTL**: 60 seconds (configurable via `CATALOG_CACHE_TTL`)
- **Driver**: Database (default, configurable)

### Cache Invalidation
Cache is invalidated automatically after ANY admin mutation:
- Category CRUD (create, update, delete)
- Menu Item CRUD
- Modifier CRUD
- Modifier Option CRUD
- Availability changes
- Soldout changes

Invalidation is handled by `MenuWriteService::invalidateCatalogCache()` which clears both cache keys.

### Cache Headers
Public catalog endpoint returns:
- `Cache-Control: public, max-age=60`
- `ETag`: MD5 hash of catalog data
- `Last-Modified`: Current timestamp

## API Endpoints

### Public Endpoints (No Authentication)

**GET /api/v1/catalog**
- Query Parameters:
  - `now` (optional): RFC3339 timestamp for availability testing
  - `include_inactive` (optional): Include inactive items (admin preview)
- Returns: Complete catalog with categories, items, modifiers, options, and `is_available_now` flags
- Headers: ETag, Last-Modified, Cache-Control

### Admin Endpoints (Bearer Authentication Required)

**Categories**
- POST /api/v1/admin/categories
- GET /api/v1/admin/categories
- GET /api/v1/admin/categories/{id}
- PATCH /api/v1/admin/categories/{id}
- DELETE /api/v1/admin/categories/{id}

**Menu Items**
- POST /api/v1/admin/menu-items
- GET /api/v1/admin/menu-items (filters: category_id, q, active)
- GET /api/v1/admin/menu-items/{id}
- PATCH /api/v1/admin/menu-items/{id}
- DELETE /api/v1/admin/menu-items/{id}

**Modifiers**
- POST /api/v1/admin/modifiers
- GET /api/v1/admin/modifiers
- GET /api/v1/admin/modifiers/{id}
- PATCH /api/v1/admin/modifiers/{id}
- DELETE /api/v1/admin/modifiers/{id}

**Modifier Options**
- POST /api/v1/admin/modifiers/{modifier}/options
- PATCH /api/v1/admin/modifiers/{modifier}/options/{option}
- DELETE /api/v1/admin/modifiers/{modifier}/options/{option}

**Item-Modifier Links**
- POST /api/v1/admin/menu-items/{item}/modifiers/{modifier}
- DELETE /api/v1/admin/menu-items/{item}/modifiers/{modifier}

**Availability**
- POST /api/v1/admin/menu-items/{item}/availabilities
- DELETE /api/v1/admin/menu-items/{item}/availabilities/{availability}

**Soldout**
- POST /api/v1/admin/menu-items/{item}/soldout
- DELETE /api/v1/admin/menu-items/{item}/soldout/{soldout}

## Error Handling

All errors follow RFC7807 format:

```json
{
  "title": "Validation Error",
  "detail": "The given data was invalid.",
  "status": 422,
  "errors": {
    "price": ["The price must be a number."]
  },
  "trace_id": "abc123"
}
```

HTTP Status Codes:
- 200: Success
- 201: Created
- 204: Deleted
- 400: Bad Request
- 401: Unauthorized
- 404: Not Found
- 409: Conflict (e.g., deleting category with active items)
- 422: Validation Error
- 500: Server Error

## Validation Rules

### Prices
- Numeric, 2 decimal places
- Regex: `/^\d+(\.\d{1,2})?$/`

### Modifiers
- `type`: enum (single, multiple)
- `type=single`: min_select ∈ {0,1}, max_select = 1
- `type=multiple`: 0 ≤ min_select ≤ max_select, max_select ≥ 1

### Availability
- `day_of_week`: integer 0-6 (Sunday-Saturday)
- `time_from < time_to`

### Business Rules
- Cannot delete category with active items (unless `force=true`)
- Price delta can be negative (discounts)

## Module 1 Integration

Module 2 is designed to work seamlessly with Module 1 (cart/checkout/payment). Integration points:

### Cart Validation
When adding items to cart (`POST /api/v1/cart/items`), the system validates:
1. Menu item exists and `is_available_now = true`
2. Selected modifier options are valid and active
3. Modifier selection satisfies min/max select rules
4. Price calculation: `base_price + sum(price_delta)`

### Stub Implementation
Minimal Module 1 stubs are provided for integration testing, including:
- Cart controller with add item endpoint
- Validation logic that checks catalog availability
- Form request for cart item validation

## Service Layer

### CatalogReadService
- `getCatalog(?string $now, bool $includeInactive)`: Assembles complete catalog
- `isItemAvailableNow(MenuItem $item, CarbonInterface $now)`: Calculates availability

### MenuWriteService
- CRUD operations for all menu entities
- Cache invalidation after mutations
- Conflict detection (e.g., deleting categories with items)

### AvailabilityService
- Manages time windows and soldout records
- Validates time window constraints
- Cache invalidation after changes

## Testing Strategy

### Feature Tests
- Public catalog endpoint (structure, cache headers, availability flags)
- Admin CRUD operations (happy paths and edge cases)
- Validation rules enforcement
- Cache invalidation verification
- Module 1 integration (cart validation)

### Test Coverage
- 6 tests, 16 assertions
- All tests passing
- Tests use RefreshDatabase trait
- In-memory SQLite for test performance

## Configuration

Environment variables in `.env.example`:
```
CATALOG_CACHE_TTL=60
APP_CURRENCY=PLN
PUBLIC_CATALOG_CACHE=true
IMAGE_BASE_URL=https://cdn.example.com/
```

## Seeders

Sample data provided:
- 5 categories (Pizza, Pasta, Beverages, Desserts, Salads)
- 7 menu items with various prices
- 2 modifiers (Size, Extra Toppings) with 8 options
- Availability windows (10:00-22:00 daily) for all items
- Modifiers attached to pizza items

## Future Enhancements (Out of Scope)

Not implemented in Module 2, potential future work:
- Multi-location price books
- Advanced taxes/VAT per region
- Item variants as separate SKUs
- Translations/i18n
- Webhooks for catalog changes
- Item bundles/combos
- A/B testing for prices
- Inventory tracking beyond soldouts

## Technical Stack

- Laravel 12
- Laravel Sanctum (Bearer token authentication)
- SQLite (testing), Database driver (caching)
- PHPUnit/Pest (testing)
- PSR-12 coding standards (Laravel Pint)

## API Standards

- Routes: `/api/v1/*`
- Format: JSON with `snake_case`
- Success envelope: `{ "data": ..., "meta": {...} }`
- Authentication: Bearer token (Sanctum) for admin, none for public
- Cache headers: ETag, Last-Modified, Cache-Control

## Completion Checklist

- ✅ All 7 database migrations created and tested
- ✅ All 6 Eloquent models with relationships
- ✅ All 3 service classes with business logic
- ✅ All 7 controllers (1 public + 6 admin)
- ✅ All 5 API resources for serialization
- ✅ All 10 form requests for validation
- ✅ Routes registered in bootstrap/app.php
- ✅ Seeders with sample data
- ✅ Tests passing (6 tests, 16 assertions)
- ✅ Lint passing (Laravel Pint)
- ✅ Documentation complete
