# Module 4: Locations & Pickup Points - Design Document

## Overview

This module implements API-only Locations & Pickup Points for X1, including restaurants/branches, pickup points, delivery zones (geofencing), delivery pricing rules, and lead times. The implementation maintains backward compatibility with Modules 1-3.

## Data Model and Relationships

### Entity Relationship Diagram

```
┌─────────────────┐
│    locations    │
├─────────────────┤
│ id              │
│ name            │
│ slug            │
│ status          │──────────────────────────────────────┐
│ phone           │                                      │
│ email           │                                      │
│ address_line1   │                                      │
│ address_line2   │                                      │
│ city            │                                      │
│ postal_code     │                                      │
│ country         │                                      │
│ lat             │                                      │
│ lng             │                                      │
│ timestamps      │                                      │
└────────┬────────┘                                      │
         │                                               │
         │ 1:N                                           │
         ▼                                               │
┌─────────────────┐                                      │
│  pickup_points  │                                      │
├─────────────────┤                                      │
│ id              │                                      │
│ location_id (FK)│                                      │
│ name            │                                      │
│ status          │                                      │
│ address_line1   │                                      │
│ address_line2   │                                      │
│ city            │                                      │
│ postal_code     │                                      │
│ country         │                                      │
│ lat             │                                      │
│ lng             │                                      │
│ instructions    │                                      │
│ timestamps      │                                      │
└─────────────────┘                                      │
                                                         │
┌─────────────────┐                                      │
│ delivery_zones  │◄─────────────────────────────────────┘
├─────────────────┤         1:N
│ id              │
│ location_id (FK)│
│ name            │
│ status          │
│ polygon_geojson │
│ priority        │
│ timestamps      │
└────────┬────────┘
         │
         │ 1:1
         ▼
┌─────────────────────────┐
│ delivery_pricing_rules  │
├─────────────────────────┤
│ id                      │
│ delivery_zone_id (FK)   │
│ fee_amount              │
│ min_order_amount        │
│ free_delivery_threshold │
│ currency                │
│ timestamps              │
└─────────────────────────┘

┌─────────────────────────┐
│  lead_time_settings     │◄─────── locations (1:1)
├─────────────────────────┤
│ id                      │
│ location_id (FK)        │
│ pickup_lead_time_min    │
│ delivery_lead_time_min  │
│ zone_extra_time_min     │
│ timestamps              │
└─────────────────────────┘
```

### Table Definitions

#### locations
Primary table for restaurant branches/locations.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| id | bigint | PK, auto-increment | Primary key |
| name | varchar(255) | NOT NULL | Location display name |
| slug | varchar(255) | NOT NULL, UNIQUE | URL-friendly identifier |
| status | varchar(20) | NOT NULL, DEFAULT 'active' | 'active' or 'inactive' |
| phone | varchar(50) | NULLABLE | Contact phone number |
| email | varchar(255) | NULLABLE | Contact email |
| address_line1 | varchar(255) | NOT NULL | Street address |
| address_line2 | varchar(255) | NULLABLE | Additional address info |
| city | varchar(100) | NOT NULL | City name |
| postal_code | varchar(20) | NOT NULL | Postal/ZIP code |
| country | char(2) | NOT NULL, DEFAULT 'PL' | ISO 3166-1 alpha-2 code |
| lat | decimal(10,7) | NOT NULL | Latitude coordinate |
| lng | decimal(10,7) | NOT NULL | Longitude coordinate |
| created_at | timestamp | | |
| updated_at | timestamp | | |

**Indexes:**
- `locations_status_index` on `status`
- `locations_slug_unique` on `slug`

#### pickup_points
Specific pickup locations within a restaurant/branch.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| id | bigint | PK, auto-increment | Primary key |
| location_id | bigint | FK -> locations.id, NOT NULL | Parent location |
| name | varchar(255) | NOT NULL | Pickup point name |
| status | varchar(20) | NOT NULL, DEFAULT 'active' | 'active' or 'inactive' |
| address_line1 | varchar(255) | NOT NULL | Street address |
| address_line2 | varchar(255) | NULLABLE | Additional address info |
| city | varchar(100) | NOT NULL | City name |
| postal_code | varchar(20) | NOT NULL | Postal/ZIP code |
| country | char(2) | NOT NULL, DEFAULT 'PL' | ISO 3166-1 alpha-2 code |
| lat | decimal(10,7) | NOT NULL | Latitude coordinate |
| lng | decimal(10,7) | NOT NULL | Longitude coordinate |
| instructions | text | NULLABLE | Pickup instructions |
| created_at | timestamp | | |
| updated_at | timestamp | | |

**Indexes:**
- `pickup_points_location_status_index` on `(location_id, status)`

#### delivery_zones
Geographic zones for delivery coverage using GeoJSON polygons.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| id | bigint | PK, auto-increment | Primary key |
| location_id | bigint | FK -> locations.id, NOT NULL | Parent location |
| name | varchar(255) | NOT NULL | Zone name (e.g., "Zone 1 - City Center") |
| status | varchar(20) | NOT NULL, DEFAULT 'active' | 'active' or 'inactive' |
| polygon_geojson | json | NOT NULL | GeoJSON Polygon coordinates |
| priority | int | NOT NULL, DEFAULT 0 | Higher priority wins on overlap |
| created_at | timestamp | | |
| updated_at | timestamp | | |

**Indexes:**
- `delivery_zones_location_status_priority_index` on `(location_id, status, priority DESC)`

#### delivery_pricing_rules
Pricing configuration for each delivery zone.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| id | bigint | PK, auto-increment | Primary key |
| delivery_zone_id | bigint | FK -> delivery_zones.id, NOT NULL, UNIQUE | One rule per zone |
| fee_amount | decimal(12,2) | NOT NULL, DEFAULT 0.00 | Delivery fee |
| min_order_amount | decimal(12,2) | NOT NULL, DEFAULT 0.00 | Minimum order value |
| free_delivery_threshold | decimal(12,2) | NULLABLE | Order value for free delivery |
| currency | char(3) | NOT NULL, DEFAULT 'PLN' | ISO 4217 currency code |
| created_at | timestamp | | |
| updated_at | timestamp | | |

**Indexes:**
- `delivery_pricing_rules_zone_unique` on `delivery_zone_id`

#### lead_time_settings
Lead time configuration for each location.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| id | bigint | PK, auto-increment | Primary key |
| location_id | bigint | FK -> locations.id, NOT NULL, UNIQUE | One setting per location |
| pickup_lead_time_min | int | NOT NULL, DEFAULT 20 | Base pickup time in minutes |
| delivery_lead_time_min | int | NOT NULL, DEFAULT 45 | Base delivery time in minutes |
| zone_extra_time_min | int | NOT NULL, DEFAULT 0 | Additional time for distant zones |
| created_at | timestamp | | |
| updated_at | timestamp | | |

**Indexes:**
- `lead_time_settings_location_unique` on `location_id`

## Geofencing Approach

### Pure PHP Point-in-Polygon (Ray Casting Algorithm)

We use a pure PHP implementation of the ray casting algorithm to avoid database-specific geospatial dependencies (no PostGIS required). This approach works with any database backend.

#### Algorithm

The ray casting algorithm determines if a point is inside a polygon by:
1. Cast a ray from the point to infinity (typically along the X-axis)
2. Count how many times the ray intersects polygon edges
3. If the count is odd, the point is inside; if even, it's outside

```php
public function isPointInPolygon(float $lat, float $lng, array $polygon): bool
{
    $vertices = count($polygon);
    $inside = false;
    
    for ($i = 0, $j = $vertices - 1; $i < $vertices; $j = $i++) {
        $xi = $polygon[$i][0]; // lng
        $yi = $polygon[$i][1]; // lat
        $xj = $polygon[$j][0]; // lng
        $yj = $polygon[$j][1]; // lat
        
        $intersect = (($yi > $lat) !== ($yj > $lat))
            && ($lng < ($xj - $xi) * ($lat - $yi) / ($yj - $yi) + $xi);
        
        if ($intersect) {
            $inside = !$inside;
        }
    }
    
    return $inside;
}
```

#### GeoJSON Polygon Format

Polygons are stored as GeoJSON with coordinates in `[longitude, latitude]` order (per GeoJSON spec):

```json
{
  "type": "Polygon",
  "coordinates": [
    [
      [21.0000, 52.2200],
      [21.0500, 52.2200],
      [21.0500, 52.2500],
      [21.0000, 52.2500],
      [21.0000, 52.2200]
    ]
  ]
}
```

**Validation Rules:**
- Must be valid GeoJSON Polygon type
- Outer ring must have at least 4 points (3 distinct + closure)
- First and last points must be identical (closed ring)
- Coordinates must be valid lat/lng ranges

#### Limitations

1. **No complex geometries**: Only simple polygons supported (no MultiPolygon, holes)
2. **No spatial indexing**: Linear search through zones; acceptable for small zone counts
3. **Boundary behavior**: Points exactly on edges are considered inside (deterministic)
4. **Precision**: Uses decimal(10,7) for ~1cm precision at equator

### Zone Matching Logic

When multiple zones match a point:
1. Filter zones by `location_id` and `status = 'active'`
2. Check point-in-polygon for each zone
3. Select zone with highest `priority` value
4. If priorities are equal, use lowest `id` (deterministic)

## Pricing Rules

### Delivery Fee Calculation

```php
public function quote(int $zoneId, string $orderSubtotal): DeliveryQuoteDTO
{
    $zone = DeliveryZone::with('pricingRule')->findOrFail($zoneId);
    $rule = $zone->pricingRule;
    
    if ($rule === null) {
        return new DeliveryQuoteDTO(
            serviceable: true,
            deliveryFee: '0.00',
            minOrderAmount: '0.00',
            freeDeliveryThreshold: null,
            currency: config('app.currency', 'PLN')
        );
    }
    
    $subtotal = (float) $orderSubtotal;
    $fee = $rule->fee_amount;
    
    // Apply free delivery threshold
    if ($rule->free_delivery_threshold !== null 
        && $subtotal >= (float) $rule->free_delivery_threshold) {
        $fee = '0.00';
    }
    
    // Check minimum order
    $meetsMinimum = $subtotal >= (float) $rule->min_order_amount;
    
    return new DeliveryQuoteDTO(
        serviceable: true,
        deliveryFee: $fee,
        minOrderAmount: $rule->min_order_amount,
        freeDeliveryThreshold: $rule->free_delivery_threshold,
        currency: $rule->currency,
        meetsMinimumOrder: $meetsMinimum
    );
}
```

### Pricing Constraints

- `fee_amount >= 0`
- `min_order_amount >= 0`
- `free_delivery_threshold` is nullable; if set, must be `>= min_order_amount`
- All monetary values use `decimal(12,2)` for precision

## Lead Time Model and ETA Computation

### Base Lead Times

Each location has configurable lead times:
- `pickup_lead_time_min`: Default 20 minutes
- `delivery_lead_time_min`: Default 45 minutes
- `zone_extra_time_min`: Additional time for all zones (default 0)

### ETA Calculation

```php
public function estimatePickup(int $locationId): int
{
    $settings = LeadTimeSetting::where('location_id', $locationId)->first();
    
    if ($settings === null) {
        return 20; // Default pickup time
    }
    
    return $settings->pickup_lead_time_min;
}

public function estimateDelivery(int $locationId, ?int $zoneId = null): int
{
    $settings = LeadTimeSetting::where('location_id', $locationId)->first();
    
    $baseTime = $settings?->delivery_lead_time_min ?? 45;
    $extraTime = $settings?->zone_extra_time_min ?? 0;
    
    return $baseTime + $extraTime;
}
```

### ETA Response

The quote endpoint returns `estimated_delivery_minutes` which can be used by checkout UX to display:
- "Estimated delivery: ~55 minutes"
- Or calculate `estimated_ready_at` timestamp

## Module 1 Checkout Integration

### Orders Table Extensions (Backward Compatible)

New nullable columns added to `orders` table:

```php
Schema::table('orders', function (Blueprint $table) {
    $table->foreignId('location_id')->nullable()->constrained()->nullOnDelete();
    $table->string('fulfillment_type', 20)->nullable(); // 'pickup' or 'delivery'
    $table->foreignId('pickup_point_id')->nullable()->constrained()->nullOnDelete();
    
    // Delivery address (denormalized for order history)
    $table->string('delivery_address_line1', 255)->nullable();
    $table->string('delivery_address_line2', 255)->nullable();
    $table->string('delivery_city', 100)->nullable();
    $table->string('delivery_postal_code', 20)->nullable();
    $table->string('delivery_country', 2)->nullable();
    $table->decimal('delivery_lat', 10, 7)->nullable();
    $table->decimal('delivery_lng', 10, 7)->nullable();
    
    // Pricing and timing
    $table->decimal('delivery_fee', 12, 2)->default(0);
    $table->integer('eta_minutes')->nullable();
    $table->timestamp('estimated_ready_at')->nullable();
});
```

### Checkout Flow

#### Pickup Order
```json
{
  "fulfillment_type": "pickup",
  "location_id": 1,
  "pickup_point_id": 2,
  "items": [...]
}
```

Validation:
1. `location_id` must exist and be active
2. `pickup_point_id` (optional) must belong to location and be active
3. Calculate ETA using `LeadTimeService::estimatePickup()`

#### Delivery Order
```json
{
  "fulfillment_type": "delivery",
  "location_id": 1,
  "delivery_address": {
    "line1": "ul. Marszalkowska 1",
    "line2": "apt 5",
    "city": "Warsaw",
    "postal_code": "00-001",
    "country": "PL",
    "lat": 52.2297,
    "lng": 21.0122
  },
  "items": [...]
}
```

Validation:
1. `location_id` must exist and be active
2. Address coordinates must be within a delivery zone
3. Order subtotal must meet minimum order requirement
4. Calculate delivery fee and ETA

### Checkout Validation Flow

```
┌─────────────────────────────────────────────────────────────┐
│                    Checkout Request                          │
└─────────────────────────────────────────────────────────────┘
                              │
                              ▼
                    ┌─────────────────┐
                    │ fulfillment_type│
                    └────────┬────────┘
                             │
              ┌──────────────┴──────────────┐
              │                             │
              ▼                             ▼
       ┌──────────┐                  ┌──────────┐
       │  pickup  │                  │ delivery │
       └────┬─────┘                  └────┬─────┘
            │                             │
            ▼                             ▼
    ┌───────────────┐           ┌─────────────────────┐
    │ Validate      │           │ Validate location   │
    │ location_id   │           │ + address + lat/lng │
    └───────┬───────┘           └──────────┬──────────┘
            │                              │
            ▼                              ▼
    ┌───────────────┐           ┌─────────────────────┐
    │ Validate      │           │ GeofenceService     │
    │ pickup_point  │           │ findMatchingZone()  │
    │ (optional)    │           └──────────┬──────────┘
    └───────┬───────┘                      │
            │                              ▼
            │                   ┌─────────────────────┐
            │                   │ Zone found?         │
            │                   └──────────┬──────────┘
            │                              │
            │              ┌───────────────┴───────────────┐
            │              │ NO                            │ YES
            │              ▼                               ▼
            │    ┌─────────────────┐           ┌─────────────────────┐
            │    │ 422 Error:      │           │ DeliveryPricingService│
            │    │ Address not     │           │ quote()              │
            │    │ serviceable     │           └──────────┬──────────┘
            │    └─────────────────┘                      │
            │                                             ▼
            │                              ┌─────────────────────┐
            │                              │ Meets min order?    │
            │                              └──────────┬──────────┘
            │                                         │
            │                         ┌───────────────┴───────────────┐
            │                         │ NO                            │ YES
            │                         ▼                               ▼
            │              ┌─────────────────┐           ┌─────────────────────┐
            │              │ 422 Error:      │           │ LeadTimeService     │
            │              │ Min order not   │           │ estimateDelivery()  │
            │              │ met             │           └──────────┬──────────┘
            │              └─────────────────┘                      │
            │                                                       │
            ▼                                                       ▼
    ┌───────────────┐                              ┌─────────────────────┐
    │ LeadTimeService│                             │ Store order with    │
    │ estimatePickup()│                            │ delivery_fee + ETA  │
    └───────┬───────┘                              └─────────────────────┘
            │
            ▼
    ┌───────────────┐
    │ Store order   │
    │ with ETA      │
    └───────────────┘
```

## Error Model and Status Codes

### RFC 7807 Error Format

All errors follow RFC 7807 Problem Details format:

```json
{
  "title": "Validation Error",
  "detail": "The given data was invalid.",
  "status": 422,
  "errors": {
    "lat": ["The lat field must be between -90 and 90."],
    "polygon_geojson": ["The polygon must have at least 4 points."]
  },
  "trace_id": "abc123xyz"
}
```

### Status Codes

| Code | Usage |
|------|-------|
| 200 | Successful GET, PATCH, delivery quote (even if not serviceable) |
| 201 | Successful POST (resource created) |
| 204 | Successful DELETE |
| 400 | Malformed request (invalid JSON, etc.) |
| 401 | Missing or invalid authentication |
| 403 | Authenticated but not authorized |
| 404 | Resource not found |
| 409 | Conflict (e.g., duplicate slug) |
| 422 | Validation error (invalid data) |
| 429 | Rate limit exceeded |
| 500 | Internal server error |

### Delivery Quote Response

The delivery quote endpoint returns 200 for both serviceable and non-serviceable addresses:

**Serviceable:**
```json
{
  "data": {
    "serviceable": true,
    "delivery_zone_id": 10,
    "delivery_fee": "7.00",
    "currency": "PLN",
    "min_order_amount": "0.00",
    "free_delivery_threshold": "80.00",
    "estimated_delivery_minutes": 55
  }
}
```

**Not Serviceable:**
```json
{
  "data": {
    "serviceable": false,
    "delivery_zone_id": null,
    "delivery_fee": null,
    "currency": "PLN",
    "min_order_amount": null,
    "free_delivery_threshold": null,
    "estimated_delivery_minutes": null
  }
}
```

## API Endpoints Summary

### Public Endpoints (No Auth)

| Method | Path | Description |
|--------|------|-------------|
| GET | /api/v1/locations | List active locations |
| GET | /api/v1/locations/{location} | Location details + pickup points summary |
| GET | /api/v1/locations/{location}/pickup-points | List active pickup points |
| POST | /api/v1/locations/{location}/delivery/quote | Get delivery quote for coordinates |

### Admin Endpoints (Bearer Auth)

| Method | Path | Description |
|--------|------|-------------|
| GET | /api/v1/admin/locations | List all locations |
| POST | /api/v1/admin/locations | Create location |
| PATCH | /api/v1/admin/locations/{id} | Update location |
| DELETE | /api/v1/admin/locations/{id} | Soft delete location |
| POST | /api/v1/admin/locations/{location}/pickup-points | Create pickup point |
| PATCH | /api/v1/admin/pickup-points/{id} | Update pickup point |
| DELETE | /api/v1/admin/pickup-points/{id} | Delete pickup point |
| POST | /api/v1/admin/locations/{location}/delivery-zones | Create delivery zone |
| PATCH | /api/v1/admin/delivery-zones/{id} | Update delivery zone |
| DELETE | /api/v1/admin/delivery-zones/{id} | Delete delivery zone |
| POST | /api/v1/admin/delivery-zones/{zone}/pricing-rules | Create/update pricing rule |
| PATCH | /api/v1/admin/pricing-rules/{id} | Update pricing rule |
| DELETE | /api/v1/admin/pricing-rules/{id} | Delete pricing rule |
| PUT | /api/v1/admin/locations/{location}/lead-time-settings | Upsert lead time settings |

## Configuration

### Environment Variables

```env
# Locations Module
APP_CURRENCY=PLN
GEO_POLYGON_ENGINE=php_ray_cast
DELIVERY_QUOTE_CACHE_TTL=60
```

## Testing Strategy

### Unit Tests
- `GeofenceServiceTest`: Point inside/outside polygon, boundary cases
- `DeliveryPricingServiceTest`: Fee calculation, thresholds, min order
- `LeadTimeServiceTest`: Base times, zone extra time

### Feature Tests
- Public endpoints: List locations, pickup points, delivery quote
- Admin CRUD: All entities with validation
- Checkout integration: Pickup and delivery flows, error cases

### Test Data
- 1 location in Warsaw
- 2 pickup points
- 2 delivery zones (overlapping with different priorities)
- Pricing rules with various configurations
- Lead time settings
