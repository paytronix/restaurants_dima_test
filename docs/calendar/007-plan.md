# Module 7: Opening Hours & Calendar - Design Document

## Overview

This module implements API-only Opening Hours & Calendar functionality for X1, including location opening hours schedules (weekly + exceptions), holidays/blackouts, fulfillment windows (pickup/delivery time slots), cut-off logic, and SLA rules. The implementation maintains backward compatibility with Modules 1-6, especially Locations (Module 4) and Checkout/Orders (Module 1).

## Data Model and Relationships

### Entity Relationship Diagram

```
┌─────────────────┐
│    locations    │
├─────────────────┤
│ id              │
│ name            │
│ slug            │
│ timezone (new)  │──────────────────────────────────────────┐
│ ...             │                                          │
└────────┬────────┘                                          │
         │                                                   │
         │ 1:N                                               │
         ▼                                                   │
┌─────────────────────┐                                      │
│   location_hours    │                                      │
├─────────────────────┤                                      │
│ id                  │                                      │
│ location_id (FK)    │                                      │
│ day_of_week (0-6)   │                                      │
│ open_time (HH:MM)   │                                      │
│ close_time (HH:MM)  │                                      │
│ fulfillment_type    │                                      │
│ is_closed           │                                      │
│ timestamps          │                                      │
└─────────────────────┘                                      │
                                                             │
┌─────────────────────────┐                                  │
│  location_exceptions    │◄─────────────────────────────────┘
├─────────────────────────┤         1:N
│ id                      │
│ location_id (FK)        │
│ date (YYYY-MM-DD)       │
│ type                    │
│ open_time (nullable)    │
│ close_time (nullable)   │
│ fulfillment_type        │
│ reason (nullable)       │
│ timestamps              │
└─────────────────────────┘

┌─────────────────────────┐
│  fulfillment_windows    │◄─────── locations (1:N per type)
├─────────────────────────┤
│ id                      │
│ location_id (FK)        │
│ fulfillment_type        │
│ slot_interval_min       │
│ slot_duration_min       │
│ min_lead_time_min       │
│ cutoff_min_before_close │
│ max_days_ahead          │
│ timestamps              │
└─────────────────────────┘
```

### Table Definitions

#### locations (Migration Update)

Add timezone column to existing locations table.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| timezone | varchar(50) | NOT NULL, DEFAULT 'Europe/Warsaw' | IANA timezone string |

#### location_hours

Weekly base schedule for each location.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| id | bigint | PK, auto-increment | Primary key |
| location_id | bigint | FK -> locations.id, NOT NULL | Parent location |
| day_of_week | tinyint | NOT NULL | 0=Sunday, 1=Monday, ..., 6=Saturday |
| open_time | time | NOT NULL | Opening time (HH:MM:SS) |
| close_time | time | NOT NULL | Closing time (HH:MM:SS) |
| fulfillment_type | varchar(20) | NOT NULL | 'pickup', 'delivery', or 'both' |
| is_closed | boolean | NOT NULL, DEFAULT false | If true, location is closed |
| created_at | timestamp | | |
| updated_at | timestamp | | |

**Indexes:**
- Unique: `(location_id, day_of_week, fulfillment_type, open_time, close_time)`
- Index: `(location_id, day_of_week)`

#### location_exceptions

Date-specific overrides for opening hours.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| id | bigint | PK, auto-increment | Primary key |
| location_id | bigint | FK -> locations.id, NOT NULL | Parent location |
| date | date | NOT NULL | Local date (YYYY-MM-DD) |
| type | varchar(30) | NOT NULL | 'closed_all_day', 'open_custom', 'blackout_window' |
| open_time | time | NULLABLE | Custom open time (for open_custom/blackout_window) |
| close_time | time | NULLABLE | Custom close time (for open_custom/blackout_window) |
| fulfillment_type | varchar(20) | NULLABLE | 'pickup', 'delivery', 'both', or null for all |
| reason | varchar(255) | NULLABLE | Human-readable reason (e.g., "Christmas Day") |
| created_at | timestamp | | |
| updated_at | timestamp | | |

**Indexes:**
- Index: `(location_id, date)`
- Index: `(location_id, date, type)`

#### fulfillment_windows

Slot generation configuration per location and fulfillment type.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| id | bigint | PK, auto-increment | Primary key |
| location_id | bigint | FK -> locations.id, NOT NULL | Parent location |
| fulfillment_type | varchar(20) | NOT NULL | 'pickup' or 'delivery' |
| slot_interval_min | int | NOT NULL, DEFAULT 15 | Minutes between slot starts |
| slot_duration_min | int | NOT NULL, DEFAULT 15 | Duration of each slot |
| min_lead_time_min | int | NOT NULL, DEFAULT 30 | SLA base prep buffer |
| cutoff_min_before_close | int | NOT NULL, DEFAULT 30 | Last order cutoff before close |
| max_days_ahead | int | NOT NULL, DEFAULT 7 | How many days ahead to show slots |
| created_at | timestamp | | |
| updated_at | timestamp | | |

**Indexes:**
- Unique: `(location_id, fulfillment_type)`

## Time Zone Strategy

### Storage and Computation

1. **Location timezone**: Stored in `locations.timezone` as IANA string (e.g., `Europe/Warsaw`, `America/New_York`)
2. **Internal storage**: All times in `location_hours` and `location_exceptions` are stored as local time (no timezone conversion needed for storage)
3. **Computation**: Slot generation and availability checks are performed in the location's local timezone
4. **API responses**: All timestamps include ISO 8601 format with timezone offset (e.g., `2026-02-02T18:30:00+01:00`)

### DST Edge Cases

**Missing Hour (Spring Forward)**
When clocks skip forward (e.g., 2:00 AM → 3:00 AM):
- Slots that would fall in the missing hour are skipped
- The next valid slot starts at the first available time after the gap

**Repeated Hour (Fall Back)**
When clocks repeat (e.g., 2:00 AM occurs twice):
- Slots are generated for the first occurrence only
- The system uses the standard time interpretation (not DST)

**Documentation**: These edge cases are handled by PHP's Carbon library with explicit timezone handling.

## Slot Generation Algorithm

### Overview

```php
public function generateSlots(
    int $locationId,
    string $date,           // YYYY-MM-DD in location local time
    string $fulfillmentType,
    ?Carbon $now = null     // For testing, defaults to Carbon::now()
): array
```

### Algorithm Steps

1. **Load configuration**:
   - Get location with timezone
   - Get fulfillment window settings (or use defaults)
   - Get weekly hours for the day of week
   - Get any exceptions for the date

2. **Determine effective hours**:
   - If exception type is `closed_all_day` → return empty array
   - If exception type is `open_custom` → use exception times
   - Otherwise → use weekly schedule

3. **Generate raw slots**:
   - Start at `open_time`
   - Create slots at `slot_interval_min` intervals
   - Stop when `slot_start + slot_duration_min > close_time`

4. **Apply blackout windows**:
   - Remove slots that overlap with any `blackout_window` exceptions

5. **Apply orderability rules**:
   - For each slot, determine if it's orderable:
     - `slot_start >= now + min_lead_time_min` (SLA buffer)
     - `slot_start <= close_time - cutoff_min_before_close` (cutoff rule)
   - Mark each slot with `is_orderable` and `reason` if not orderable

6. **Return slots array** with:
   - `slot_start` (ISO 8601 with offset)
   - `slot_end` (ISO 8601 with offset)
   - `is_orderable` (boolean)
   - `reason` (string, null if orderable)

### Cross-Midnight Hours

If `close_time < open_time` (e.g., 18:00 - 02:00):
- This is explicitly **not supported** in the initial implementation
- Validation will reject such configurations
- Future enhancement: Split into two separate hour entries

## Cut-off and SLA Rules

### Cut-off Logic

The cut-off determines the latest time an order can be placed for a given slot:

```
orderable = slot_start <= close_time - cutoff_min_before_close
```

Example: If close_time is 22:00 and cutoff is 30 minutes, the last orderable slot starts at 21:30.

### SLA Buffer (Lead Time)

The SLA buffer ensures adequate preparation time:

```
orderable = slot_start >= now + min_lead_time_min
```

Example: If now is 18:00 and min_lead_time is 30 minutes, the earliest orderable slot is 18:30.

### Combined Rule

A slot is orderable only if BOTH conditions are met:
```
is_orderable = (slot_start >= now + min_lead_time_min) 
            && (slot_start <= close_time - cutoff_min_before_close)
```

## Exception Precedence

Exceptions are applied in the following order:

1. **`closed_all_day`**: Overrides everything; location is closed for the entire day
2. **`open_custom`**: Replaces weekly hours with custom hours for that date
3. **`blackout_window`**: Removes specific time ranges from availability (applied after hours are determined)

Multiple exceptions can exist for the same date:
- One `closed_all_day` or `open_custom` (mutually exclusive)
- Multiple `blackout_window` entries (additive)

## API Endpoints

### Public Endpoints (No Auth, Cacheable)

#### GET /api/v1/locations/{location}/hours

Returns weekly schedule and timezone.

**Response:**
```json
{
  "data": {
    "location_id": 1,
    "timezone": "Europe/Warsaw",
    "weekly_hours": [
      {
        "day_of_week": 0,
        "day_name": "Sunday",
        "is_closed": true,
        "hours": []
      },
      {
        "day_of_week": 1,
        "day_name": "Monday",
        "is_closed": false,
        "hours": [
          {
            "open_time": "10:00",
            "close_time": "22:00",
            "fulfillment_type": "both"
          }
        ]
      }
    ]
  }
}
```

#### GET /api/v1/locations/{location}/calendar

Returns effective open/closed days for a date range.

**Query Parameters:**
- `from` (required): Start date YYYY-MM-DD
- `to` (required): End date YYYY-MM-DD
- `fulfillment_type` (optional): 'pickup' or 'delivery'

**Response:**
```json
{
  "data": {
    "location_id": 1,
    "timezone": "Europe/Warsaw",
    "from": "2026-02-01",
    "to": "2026-02-07",
    "days": [
      {
        "date": "2026-02-01",
        "day_of_week": 0,
        "is_open": false,
        "reason": "Sunday - Closed",
        "hours": []
      },
      {
        "date": "2026-02-02",
        "day_of_week": 1,
        "is_open": true,
        "reason": null,
        "hours": [
          {
            "open_time": "10:00",
            "close_time": "22:00",
            "fulfillment_type": "both"
          }
        ],
        "exceptions": []
      }
    ]
  },
  "meta": {
    "total_days": 7,
    "open_days": 6
  }
}
```

#### GET /api/v1/locations/{location}/slots

Returns available orderable slots for a specific date.

**Query Parameters:**
- `date` (required): Date YYYY-MM-DD
- `fulfillment_type` (required): 'pickup' or 'delivery'
- `now` (optional): RFC3339 timestamp for testing

**Response Headers:**
- `Cache-Control: public, max-age=60`
- `ETag: "..."` (based on content hash)

**Response:**
```json
{
  "data": {
    "location_id": 1,
    "date": "2026-02-02",
    "timezone": "Europe/Warsaw",
    "fulfillment_type": "pickup",
    "slots": [
      {
        "slot_start": "2026-02-02T10:00:00+01:00",
        "slot_end": "2026-02-02T10:15:00+01:00",
        "is_orderable": false,
        "reason": "Past cutoff time"
      },
      {
        "slot_start": "2026-02-02T18:30:00+01:00",
        "slot_end": "2026-02-02T18:45:00+01:00",
        "is_orderable": true,
        "reason": null
      }
    ]
  },
  "meta": {
    "total_slots": 48,
    "orderable_slots": 20
  }
}
```

#### POST /api/v1/locations/{location}/validate-fulfillment

Validates a requested fulfillment time.

**Request:**
```json
{
  "fulfillment_type": "pickup",
  "requested_at": "2026-02-02T18:30:00+01:00"
}
```

**Response:**
```json
{
  "data": {
    "valid": true,
    "normalized_requested_at": "2026-02-02T18:30:00+01:00",
    "earliest_possible_at": "2026-02-02T18:00:00+01:00",
    "reason": null
  }
}
```

### Admin Endpoints (Bearer Auth Required)

#### Weekly Hours CRUD

**PUT /api/v1/admin/locations/{location}/hours**

Bulk replace weekly hours.

**Request:**
```json
{
  "hours": [
    {
      "day_of_week": 1,
      "open_time": "10:00",
      "close_time": "22:00",
      "fulfillment_type": "both",
      "is_closed": false
    }
  ]
}
```

**GET /api/v1/admin/locations/{location}/hours**

Returns all weekly hours for admin view.

#### Exceptions CRUD

**POST /api/v1/admin/locations/{location}/exceptions**

Create a new exception.

**Request:**
```json
{
  "date": "2026-12-25",
  "type": "closed_all_day",
  "reason": "Christmas Day"
}
```

**PATCH /api/v1/admin/exceptions/{id}**

Update an exception.

**DELETE /api/v1/admin/exceptions/{id}**

Delete an exception.

**GET /api/v1/admin/locations/{location}/exceptions**

List exceptions with optional date range filter.

**Query Parameters:**
- `from` (optional): Start date
- `to` (optional): End date

#### Fulfillment Windows

**PUT /api/v1/admin/locations/{location}/fulfillment-windows**

Create or update fulfillment window settings.

**Request:**
```json
{
  "fulfillment_type": "pickup",
  "slot_interval_min": 15,
  "slot_duration_min": 15,
  "min_lead_time_min": 30,
  "cutoff_min_before_close": 30,
  "max_days_ahead": 7
}
```

## Error Handling

### Error Response Format (RFC7807)

```json
{
  "title": "Validation Error",
  "detail": "The requested fulfillment time is not available.",
  "status": 422,
  "errors": {
    "requested_at": ["The selected time slot is not orderable."]
  },
  "trace_id": "abc123"
}
```

### Error Codes

| Status | Title | When |
|--------|-------|------|
| 400 | Bad Request | Malformed request body |
| 404 | Not Found | Location not found or inactive |
| 409 | Conflict | Overlapping schedule entries |
| 422 | Validation Error | Invalid input or business rule violation |

## Caching Strategy

### Public Endpoints

- **Hours endpoint**: Cache for 5 minutes (rarely changes)
- **Calendar endpoint**: Cache for 1 minute (may change with exceptions)
- **Slots endpoint**: Cache for 60 seconds with ETag support

### Cache Invalidation

When admin updates hours/exceptions/windows:
- Clear relevant cache keys
- Return fresh data in response

## Integration with Checkout (Module 1)

### Order Fields (Optional Enhancement)

If checkout integration is needed, add nullable fields to orders:

```php
$table->timestamp('requested_fulfillment_at')->nullable();
$table->timestamp('earliest_fulfillment_at')->nullable();
```

### Validation Flow

When checkout receives `requested_fulfillment_at`:

1. Call `SlotGeneratorService::isSlotOrderable()`
2. If invalid → return 422 with details
3. If valid → store on order and proceed

## Service Layer

### LocationCalendarService

Builds effective schedule for a date range combining weekly hours and exceptions.

```php
class LocationCalendarService
{
    public function getEffectiveSchedule(
        int $locationId,
        Carbon $from,
        Carbon $to,
        ?string $fulfillmentType = null
    ): array;
    
    public function getEffectiveHoursForDate(
        int $locationId,
        Carbon $date,
        ?string $fulfillmentType = null
    ): array;
}
```

### SlotGeneratorService

Generates fulfillment slots based on hours and window settings.

```php
class SlotGeneratorService
{
    public function generateSlots(
        int $locationId,
        string $date,
        string $fulfillmentType,
        ?Carbon $now = null
    ): array;
    
    public function isSlotOrderable(
        int $locationId,
        Carbon $slotStart,
        string $fulfillmentType,
        ?Carbon $now = null
    ): bool;
}
```

### CutoffService

Determines if a slot is orderable given current time and rules.

```php
class CutoffService
{
    public function isWithinCutoff(
        Carbon $slotStart,
        Carbon $closeTime,
        int $cutoffMinutes
    ): bool;
    
    public function getLastOrderableSlot(
        Carbon $closeTime,
        int $cutoffMinutes
    ): Carbon;
}
```

### SlaService

Computes earliest possible fulfillment time.

```php
class SlaService
{
    public function getEarliestFulfillmentTime(
        int $locationId,
        string $fulfillmentType,
        ?Carbon $now = null
    ): Carbon;
    
    public function meetsLeadTime(
        Carbon $requestedTime,
        int $minLeadTimeMin,
        ?Carbon $now = null
    ): bool;
}
```

## Testing Strategy

### Unit Tests

- Weekly schedule retrieval
- Exception override logic
- Slot generation algorithm
- Cut-off calculations
- SLA buffer calculations

### Feature Tests

- Public API endpoints with various scenarios
- Admin CRUD operations
- Validation error responses
- Cache headers verification

### Edge Cases to Test

1. Location with no hours configured
2. Date with `closed_all_day` exception
3. Date with `open_custom` exception
4. Multiple `blackout_window` exceptions
5. Slot at exact cutoff boundary
6. Slot at exact SLA boundary
7. DST transition day (documented, may be skipped)
8. Request for date beyond `max_days_ahead`

## Deliverables Checklist

- [ ] Design document (`/docs/calendar/007-plan.md`)
- [ ] Migration: Add `timezone` to locations
- [ ] Migration: Create `location_hours` table
- [ ] Migration: Create `location_exceptions` table
- [ ] Migration: Create `fulfillment_windows` table
- [ ] Model: `LocationHour`
- [ ] Model: `LocationException`
- [ ] Model: `FulfillmentWindow`
- [ ] Service: `LocationCalendarService`
- [ ] Service: `SlotGeneratorService`
- [ ] Service: `CutoffService`
- [ ] Service: `SlaService`
- [ ] Controller: `CalendarController` (public)
- [ ] Controller: `Admin\CalendarController` (admin)
- [ ] Resources: `WeeklyHoursResource`, `CalendarDayResource`, `SlotResource`, etc.
- [ ] Form Requests for validation
- [ ] Routes in `api.php`
- [ ] OpenAPI documentation (`/docs/openapi/calendar.yaml`)
- [ ] Feature tests for all endpoints
- [ ] Unit tests for services
