<?php

namespace Tests\Feature\Api\V1;

use App\Models\FulfillmentWindow;
use App\Models\Location;
use App\Models\LocationException;
use App\Models\LocationHour;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminCalendarTest extends TestCase
{
    use RefreshDatabase;

    private Location $location;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();

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

    public function test_get_hours_requires_authentication(): void
    {
        $response = $this->getJson("/api/v1/admin/locations/{$this->location->id}/hours");

        $response->assertStatus(401);
    }

    public function test_get_hours_returns_weekly_schedule(): void
    {
        LocationHour::create([
            'location_id' => $this->location->id,
            'day_of_week' => 1,
            'open_time' => '10:00',
            'close_time' => '22:00',
            'fulfillment_type' => 'both',
            'is_closed' => false,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/admin/locations/{$this->location->id}/hours");

        $response->assertStatus(200)
            ->assertJsonPath('data.location_id', $this->location->id)
            ->assertJsonPath('data.timezone', 'Europe/Warsaw')
            ->assertJsonCount(7, 'data.weekly_hours');
    }

    public function test_update_hours_replaces_weekly_schedule(): void
    {
        LocationHour::create([
            'location_id' => $this->location->id,
            'day_of_week' => 1,
            'open_time' => '10:00',
            'close_time' => '22:00',
            'fulfillment_type' => 'both',
            'is_closed' => false,
        ]);

        $response = $this->actingAs($this->user)
            ->putJson("/api/v1/admin/locations/{$this->location->id}/hours", [
                'hours' => [
                    [
                        'day_of_week' => 1,
                        'open_time' => '09:00',
                        'close_time' => '21:00',
                        'fulfillment_type' => 'pickup',
                        'is_closed' => false,
                    ],
                    [
                        'day_of_week' => 2,
                        'open_time' => '09:00',
                        'close_time' => '21:00',
                        'fulfillment_type' => 'both',
                        'is_closed' => false,
                    ],
                ],
            ]);

        $response->assertStatus(200);

        $this->assertDatabaseCount('location_hours', 2);
        $this->assertDatabaseHas('location_hours', [
            'location_id' => $this->location->id,
            'day_of_week' => 1,
            'open_time' => '09:00',
            'fulfillment_type' => 'pickup',
        ]);
    }

    public function test_update_hours_validates_input(): void
    {
        $response = $this->actingAs($this->user)
            ->putJson("/api/v1/admin/locations/{$this->location->id}/hours", [
                'hours' => [
                    [
                        'day_of_week' => 10,
                        'open_time' => 'invalid',
                        'close_time' => '21:00',
                        'fulfillment_type' => 'invalid',
                    ],
                ],
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'hours.0.day_of_week',
                'hours.0.open_time',
                'hours.0.fulfillment_type',
            ]);
    }

    public function test_list_exceptions_returns_exceptions(): void
    {
        LocationException::create([
            'location_id' => $this->location->id,
            'date' => '2026-12-25',
            'type' => 'closed_all_day',
            'reason' => 'Christmas Day',
        ]);

        LocationException::create([
            'location_id' => $this->location->id,
            'date' => '2026-12-26',
            'type' => 'open_custom',
            'open_time' => '12:00',
            'close_time' => '18:00',
            'reason' => 'Boxing Day',
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/admin/locations/{$this->location->id}/exceptions");

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 2);
    }

    public function test_list_exceptions_filters_by_date_range(): void
    {
        LocationException::create([
            'location_id' => $this->location->id,
            'date' => '2026-12-25',
            'type' => 'closed_all_day',
            'reason' => 'Christmas Day',
        ]);

        LocationException::create([
            'location_id' => $this->location->id,
            'date' => '2027-01-01',
            'type' => 'closed_all_day',
            'reason' => 'New Year',
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/admin/locations/{$this->location->id}/exceptions?from=2026-12-01&to=2026-12-31");

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.reason', 'Christmas Day');
    }

    public function test_store_exception_creates_closed_all_day(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/admin/locations/{$this->location->id}/exceptions", [
                'date' => '2026-12-25',
                'type' => 'closed_all_day',
                'reason' => 'Christmas Day',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.date', '2026-12-25')
            ->assertJsonPath('data.type', 'closed_all_day')
            ->assertJsonPath('data.reason', 'Christmas Day');

        $this->assertDatabaseHas('location_exceptions', [
            'location_id' => $this->location->id,
            'type' => 'closed_all_day',
        ]);
    }

    public function test_store_exception_creates_open_custom(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/admin/locations/{$this->location->id}/exceptions", [
                'date' => '2026-12-26',
                'type' => 'open_custom',
                'open_time' => '12:00',
                'close_time' => '18:00',
                'reason' => 'Boxing Day',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.type', 'open_custom')
            ->assertJsonPath('data.open_time', '12:00')
            ->assertJsonPath('data.close_time', '18:00');
    }

    public function test_store_exception_creates_blackout_window(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/admin/locations/{$this->location->id}/exceptions", [
                'date' => '2026-02-15',
                'type' => 'blackout_window',
                'open_time' => '14:00',
                'close_time' => '16:00',
                'reason' => 'Staff meeting',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.type', 'blackout_window')
            ->assertJsonPath('data.open_time', '14:00')
            ->assertJsonPath('data.close_time', '16:00');
    }

    public function test_store_exception_validates_input(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/admin/locations/{$this->location->id}/exceptions", [
                'date' => 'invalid',
                'type' => 'invalid_type',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['date', 'type']);
    }

    public function test_update_exception_updates_fields(): void
    {
        $exception = LocationException::create([
            'location_id' => $this->location->id,
            'date' => '2026-12-25',
            'type' => 'closed_all_day',
            'reason' => 'Christmas Day',
        ]);

        $response = $this->actingAs($this->user)
            ->patchJson("/api/v1/admin/exceptions/{$exception->id}", [
                'reason' => 'Updated reason',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.reason', 'Updated reason');

        $this->assertDatabaseHas('location_exceptions', [
            'id' => $exception->id,
            'reason' => 'Updated reason',
        ]);
    }

    public function test_destroy_exception_deletes_exception(): void
    {
        $exception = LocationException::create([
            'location_id' => $this->location->id,
            'date' => '2026-12-25',
            'type' => 'closed_all_day',
            'reason' => 'Christmas Day',
        ]);

        $response = $this->actingAs($this->user)
            ->deleteJson("/api/v1/admin/exceptions/{$exception->id}");

        $response->assertStatus(204);

        $this->assertDatabaseMissing('location_exceptions', [
            'id' => $exception->id,
        ]);
    }

    public function test_get_fulfillment_windows_returns_windows(): void
    {
        FulfillmentWindow::create([
            'location_id' => $this->location->id,
            'fulfillment_type' => 'pickup',
            'slot_interval_min' => 15,
            'slot_duration_min' => 15,
            'min_lead_time_min' => 30,
            'cutoff_min_before_close' => 30,
            'max_days_ahead' => 7,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/admin/locations/{$this->location->id}/fulfillment-windows");

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.fulfillment_type', 'pickup')
            ->assertJsonPath('data.0.slot_interval_min', 15);
    }

    public function test_update_fulfillment_window_creates_new_window(): void
    {
        $response = $this->actingAs($this->user)
            ->putJson("/api/v1/admin/locations/{$this->location->id}/fulfillment-windows", [
                'fulfillment_type' => 'pickup',
                'slot_interval_min' => 20,
                'slot_duration_min' => 20,
                'min_lead_time_min' => 45,
                'cutoff_min_before_close' => 45,
                'max_days_ahead' => 14,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.fulfillment_type', 'pickup')
            ->assertJsonPath('data.slot_interval_min', 20)
            ->assertJsonPath('data.min_lead_time_min', 45);

        $this->assertDatabaseHas('fulfillment_windows', [
            'location_id' => $this->location->id,
            'fulfillment_type' => 'pickup',
            'slot_interval_min' => 20,
        ]);
    }

    public function test_update_fulfillment_window_updates_existing_window(): void
    {
        FulfillmentWindow::create([
            'location_id' => $this->location->id,
            'fulfillment_type' => 'pickup',
            'slot_interval_min' => 15,
            'slot_duration_min' => 15,
            'min_lead_time_min' => 30,
            'cutoff_min_before_close' => 30,
            'max_days_ahead' => 7,
        ]);

        $response = $this->actingAs($this->user)
            ->putJson("/api/v1/admin/locations/{$this->location->id}/fulfillment-windows", [
                'fulfillment_type' => 'pickup',
                'slot_interval_min' => 30,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.slot_interval_min', 30);

        $this->assertDatabaseCount('fulfillment_windows', 1);
    }

    public function test_update_fulfillment_window_validates_input(): void
    {
        $response = $this->actingAs($this->user)
            ->putJson("/api/v1/admin/locations/{$this->location->id}/fulfillment-windows", [
                'fulfillment_type' => 'invalid',
                'slot_interval_min' => 1,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['fulfillment_type', 'slot_interval_min']);
    }
}
