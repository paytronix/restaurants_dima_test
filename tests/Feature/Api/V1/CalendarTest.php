<?php

namespace Tests\Feature\Api\V1;

use App\Models\FulfillmentWindow;
use App\Models\Location;
use App\Models\LocationException;
use App\Models\LocationHour;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CalendarTest extends TestCase
{
    use RefreshDatabase;

    private Location $location;

    protected function setUp(): void
    {
        parent::setUp();

        $this->location = Location::create([
            'name' => 'Warsaw Central',
            'slug' => 'warsaw-central',
            'status' => 'active',
            'phone' => '+48 22 123 4567',
            'email' => 'warsaw@example.com',
            'address_line1' => 'ul. Marszalkowska 100',
            'city' => 'Warsaw',
            'postal_code' => '00-001',
            'country' => 'PL',
            'lat' => 52.2297,
            'lng' => 21.0122,
            'timezone' => 'Europe/Warsaw',
        ]);
    }

    public function test_get_weekly_hours_returns_empty_schedule_when_no_hours_configured(): void
    {
        $response = $this->getJson("/api/v1/locations/{$this->location->id}/hours");

        $response->assertStatus(200)
            ->assertJsonPath('data.location_id', $this->location->id)
            ->assertJsonPath('data.timezone', 'Europe/Warsaw')
            ->assertJsonCount(7, 'data.weekly_hours');

        foreach ($response->json('data.weekly_hours') as $day) {
            $this->assertTrue($day['is_closed']);
            $this->assertEmpty($day['hours']);
        }
    }

    public function test_get_weekly_hours_returns_configured_schedule(): void
    {
        LocationHour::create([
            'location_id' => $this->location->id,
            'day_of_week' => 1,
            'open_time' => '10:00',
            'close_time' => '22:00',
            'fulfillment_type' => 'both',
            'is_closed' => false,
        ]);

        $response = $this->getJson("/api/v1/locations/{$this->location->id}/hours");

        $response->assertStatus(200)
            ->assertJsonPath('data.location_id', $this->location->id);

        $monday = collect($response->json('data.weekly_hours'))->firstWhere('day_of_week', 1);
        $this->assertFalse($monday['is_closed']);
        $this->assertCount(1, $monday['hours']);
        $this->assertEquals('10:00', $monday['hours'][0]['open_time']);
        $this->assertEquals('22:00', $monday['hours'][0]['close_time']);
    }

    public function test_get_weekly_hours_returns_404_for_inactive_location(): void
    {
        $inactiveLocation = Location::create([
            'name' => 'Inactive Location',
            'slug' => 'inactive-location',
            'status' => 'inactive',
            'address_line1' => 'Test Address',
            'city' => 'Test City',
            'postal_code' => '00-002',
            'country' => 'PL',
            'lat' => 52.0000,
            'lng' => 21.0000,
        ]);

        $response = $this->getJson("/api/v1/locations/{$inactiveLocation->id}/hours");

        $response->assertStatus(404);
    }

    public function test_get_calendar_returns_effective_schedule_for_date_range(): void
    {
        LocationHour::create([
            'location_id' => $this->location->id,
            'day_of_week' => 1,
            'open_time' => '10:00',
            'close_time' => '22:00',
            'fulfillment_type' => 'both',
            'is_closed' => false,
        ]);

        $response = $this->getJson("/api/v1/locations/{$this->location->id}/calendar?from=2026-02-02&to=2026-02-08");

        $response->assertStatus(200)
            ->assertJsonPath('data.location_id', $this->location->id)
            ->assertJsonPath('data.from', '2026-02-02')
            ->assertJsonPath('data.to', '2026-02-08')
            ->assertJsonCount(7, 'data.days')
            ->assertJsonStructure([
                'data' => [
                    'location_id',
                    'timezone',
                    'from',
                    'to',
                    'days' => [
                        '*' => [
                            'date',
                            'day_of_week',
                            'day_name',
                            'is_open',
                            'reason',
                            'hours',
                            'exceptions',
                        ],
                    ],
                ],
                'meta' => [
                    'total_days',
                    'open_days',
                ],
            ]);
    }

    public function test_get_calendar_validates_required_parameters(): void
    {
        $response = $this->getJson("/api/v1/locations/{$this->location->id}/calendar");

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['from', 'to']);
    }

    public function test_closed_all_day_exception_overrides_weekly_hours(): void
    {
        LocationHour::create([
            'location_id' => $this->location->id,
            'day_of_week' => 1,
            'open_time' => '10:00',
            'close_time' => '22:00',
            'fulfillment_type' => 'both',
            'is_closed' => false,
        ]);

        LocationException::create([
            'location_id' => $this->location->id,
            'date' => '2026-02-02',
            'type' => 'closed_all_day',
            'reason' => 'Holiday',
        ]);

        $response = $this->getJson("/api/v1/locations/{$this->location->id}/calendar?from=2026-02-02&to=2026-02-02");

        $response->assertStatus(200);

        $day = $response->json('data.days.0');
        $this->assertFalse($day['is_open']);
        $this->assertEquals('Holiday', $day['reason']);
    }

    public function test_open_custom_exception_overrides_weekly_hours(): void
    {
        LocationHour::create([
            'location_id' => $this->location->id,
            'day_of_week' => 1,
            'open_time' => '10:00',
            'close_time' => '22:00',
            'fulfillment_type' => 'both',
            'is_closed' => false,
        ]);

        LocationException::create([
            'location_id' => $this->location->id,
            'date' => '2026-02-02',
            'type' => 'open_custom',
            'open_time' => '12:00',
            'close_time' => '18:00',
            'reason' => 'Special hours',
        ]);

        $response = $this->getJson("/api/v1/locations/{$this->location->id}/calendar?from=2026-02-02&to=2026-02-02");

        $response->assertStatus(200);

        $day = $response->json('data.days.0');
        $this->assertTrue($day['is_open']);
        $this->assertEquals('12:00', $day['hours'][0]['open_time']);
        $this->assertEquals('18:00', $day['hours'][0]['close_time']);
    }

    public function test_get_slots_returns_available_slots(): void
    {
        LocationHour::create([
            'location_id' => $this->location->id,
            'day_of_week' => 1,
            'open_time' => '10:00',
            'close_time' => '22:00',
            'fulfillment_type' => 'both',
            'is_closed' => false,
        ]);

        FulfillmentWindow::create([
            'location_id' => $this->location->id,
            'fulfillment_type' => 'pickup',
            'slot_interval_min' => 15,
            'slot_duration_min' => 15,
            'min_lead_time_min' => 30,
            'cutoff_min_before_close' => 30,
            'max_days_ahead' => 7,
        ]);

        Carbon::setTestNow(Carbon::parse('2026-02-02 10:00:00', 'Europe/Warsaw'));

        $response = $this->getJson(
            "/api/v1/locations/{$this->location->id}/slots?date=2026-02-02&fulfillment_type=pickup"
        );

        $response->assertStatus(200)
            ->assertJsonPath('data.location_id', $this->location->id)
            ->assertJsonPath('data.date', '2026-02-02')
            ->assertJsonPath('data.fulfillment_type', 'pickup')
            ->assertJsonStructure([
                'data' => [
                    'location_id',
                    'date',
                    'timezone',
                    'fulfillment_type',
                    'slots' => [
                        '*' => [
                            'slot_start',
                            'slot_end',
                            'is_orderable',
                            'reason',
                        ],
                    ],
                ],
                'meta' => [
                    'total_slots',
                    'orderable_slots',
                ],
            ])
            ->assertHeader('ETag');

        $this->assertStringContainsString('max-age=60', $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('public', $response->headers->get('Cache-Control'));

        Carbon::setTestNow();
    }

    public function test_get_slots_validates_required_parameters(): void
    {
        $response = $this->getJson("/api/v1/locations/{$this->location->id}/slots");

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['date', 'fulfillment_type']);
    }

    public function test_get_slots_returns_empty_for_closed_day(): void
    {
        $response = $this->getJson(
            "/api/v1/locations/{$this->location->id}/slots?date=2026-02-02&fulfillment_type=pickup"
        );

        $response->assertStatus(200)
            ->assertJsonPath('data.slots', [])
            ->assertJsonPath('meta.total_slots', 0);
    }

    public function test_slots_respect_lead_time(): void
    {
        LocationHour::create([
            'location_id' => $this->location->id,
            'day_of_week' => 1,
            'open_time' => '10:00',
            'close_time' => '22:00',
            'fulfillment_type' => 'both',
            'is_closed' => false,
        ]);

        FulfillmentWindow::create([
            'location_id' => $this->location->id,
            'fulfillment_type' => 'pickup',
            'slot_interval_min' => 15,
            'slot_duration_min' => 15,
            'min_lead_time_min' => 60,
            'cutoff_min_before_close' => 30,
            'max_days_ahead' => 7,
        ]);

        $now = Carbon::parse('2026-02-02 12:00:00', 'Europe/Warsaw');
        Carbon::setTestNow($now);

        $response = $this->getJson(
            "/api/v1/locations/{$this->location->id}/slots?date=2026-02-02&fulfillment_type=pickup"
        );

        $response->assertStatus(200);

        $slots = collect($response->json('data.slots'));

        $slotsBeforeLeadTime = $slots->filter(function ($slot) use ($now) {
            $slotStart = Carbon::parse($slot['slot_start']);

            return $slotStart->lt($now->copy()->addMinutes(60));
        });

        foreach ($slotsBeforeLeadTime as $slot) {
            $this->assertFalse($slot['is_orderable']);
            $this->assertStringContainsString('lead time', $slot['reason']);
        }

        Carbon::setTestNow();
    }

    public function test_slots_respect_cutoff(): void
    {
        LocationHour::create([
            'location_id' => $this->location->id,
            'day_of_week' => 1,
            'open_time' => '10:00',
            'close_time' => '22:00',
            'fulfillment_type' => 'both',
            'is_closed' => false,
        ]);

        FulfillmentWindow::create([
            'location_id' => $this->location->id,
            'fulfillment_type' => 'pickup',
            'slot_interval_min' => 15,
            'slot_duration_min' => 15,
            'min_lead_time_min' => 30,
            'cutoff_min_before_close' => 60,
            'max_days_ahead' => 7,
        ]);

        $now = Carbon::parse('2026-02-02 10:00:00', 'Europe/Warsaw');
        Carbon::setTestNow($now);

        $response = $this->getJson(
            "/api/v1/locations/{$this->location->id}/slots?date=2026-02-02&fulfillment_type=pickup"
        );

        $response->assertStatus(200);

        $slots = collect($response->json('data.slots'));

        $closeTime = Carbon::parse('2026-02-02 22:00:00', 'Europe/Warsaw');
        $cutoffTime = $closeTime->copy()->subMinutes(60);

        $slotsAfterCutoff = $slots->filter(function ($slot) use ($cutoffTime) {
            $slotStart = Carbon::parse($slot['slot_start']);

            return $slotStart->gt($cutoffTime);
        });

        foreach ($slotsAfterCutoff as $slot) {
            $this->assertFalse($slot['is_orderable']);
            $this->assertStringContainsString('cutoff', $slot['reason']);
        }

        Carbon::setTestNow();
    }

    public function test_blackout_window_removes_slots(): void
    {
        LocationHour::create([
            'location_id' => $this->location->id,
            'day_of_week' => 1,
            'open_time' => '10:00',
            'close_time' => '22:00',
            'fulfillment_type' => 'both',
            'is_closed' => false,
        ]);

        LocationException::create([
            'location_id' => $this->location->id,
            'date' => '2026-02-02',
            'type' => 'blackout_window',
            'open_time' => '14:00',
            'close_time' => '16:00',
            'reason' => 'Staff meeting',
        ]);

        FulfillmentWindow::create([
            'location_id' => $this->location->id,
            'fulfillment_type' => 'pickup',
            'slot_interval_min' => 15,
            'slot_duration_min' => 15,
            'min_lead_time_min' => 30,
            'cutoff_min_before_close' => 30,
            'max_days_ahead' => 7,
        ]);

        $now = Carbon::parse('2026-02-02 10:00:00', 'Europe/Warsaw');
        Carbon::setTestNow($now);

        $response = $this->getJson(
            "/api/v1/locations/{$this->location->id}/slots?date=2026-02-02&fulfillment_type=pickup"
        );

        $response->assertStatus(200);

        $slots = collect($response->json('data.slots'));

        $blackoutSlots = $slots->filter(function ($slot) {
            $slotStart = Carbon::parse($slot['slot_start']);
            $slotEnd = Carbon::parse($slot['slot_end']);

            $blackoutStart = Carbon::parse('2026-02-02 14:00:00', 'Europe/Warsaw');
            $blackoutEnd = Carbon::parse('2026-02-02 16:00:00', 'Europe/Warsaw');

            return $slotStart->lt($blackoutEnd) && $slotEnd->gt($blackoutStart);
        });

        foreach ($blackoutSlots as $slot) {
            $this->assertFalse($slot['is_orderable']);
            $this->assertStringContainsString('blackout', $slot['reason']);
        }

        Carbon::setTestNow();
    }

    public function test_validate_fulfillment_returns_valid_for_orderable_slot(): void
    {
        LocationHour::create([
            'location_id' => $this->location->id,
            'day_of_week' => 1,
            'open_time' => '10:00',
            'close_time' => '22:00',
            'fulfillment_type' => 'both',
            'is_closed' => false,
        ]);

        FulfillmentWindow::create([
            'location_id' => $this->location->id,
            'fulfillment_type' => 'pickup',
            'slot_interval_min' => 15,
            'slot_duration_min' => 15,
            'min_lead_time_min' => 30,
            'cutoff_min_before_close' => 30,
            'max_days_ahead' => 7,
        ]);

        Carbon::setTestNow(Carbon::parse('2026-02-02 10:00:00', 'Europe/Warsaw'));

        $response = $this->postJson("/api/v1/locations/{$this->location->id}/validate-fulfillment", [
            'fulfillment_type' => 'pickup',
            'requested_at' => '2026-02-02T14:00:00+01:00',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.valid', true)
            ->assertJsonPath('data.reason', null)
            ->assertJsonStructure([
                'data' => [
                    'valid',
                    'normalized_requested_at',
                    'earliest_possible_at',
                    'reason',
                ],
            ]);

        Carbon::setTestNow();
    }

    public function test_validate_fulfillment_returns_invalid_for_closed_day(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-02-02 10:00:00', 'Europe/Warsaw'));

        $response = $this->postJson("/api/v1/locations/{$this->location->id}/validate-fulfillment", [
            'fulfillment_type' => 'pickup',
            'requested_at' => '2026-02-02T14:00:00+01:00',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.valid', false);

        Carbon::setTestNow();
    }

    public function test_validate_fulfillment_validates_required_parameters(): void
    {
        $response = $this->postJson("/api/v1/locations/{$this->location->id}/validate-fulfillment", []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['fulfillment_type', 'requested_at']);
    }
}
