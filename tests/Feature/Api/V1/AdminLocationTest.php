<?php

namespace Tests\Feature\Api\V1;

use App\Models\DeliveryZone;
use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminLocationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
    }

    public function test_admin_can_create_location(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/v1/admin/locations', [
            'name' => 'New Location',
            'slug' => 'new-location',
            'address_line1' => 'Test Address',
            'city' => 'Test City',
            'postal_code' => '00-001',
            'country' => 'PL',
            'lat' => 52.2297,
            'lng' => 21.0122,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'New Location')
            ->assertJsonPath('data.slug', 'new-location')
            ->assertJsonPath('data.status', 'active');

        $this->assertDatabaseHas('locations', [
            'name' => 'New Location',
            'slug' => 'new-location',
        ]);
    }

    public function test_admin_can_update_location(): void
    {
        $location = Location::create([
            'name' => 'Original Name',
            'slug' => 'original-slug',
            'status' => 'active',
            'address_line1' => 'Test Address',
            'city' => 'Test City',
            'postal_code' => '00-001',
            'country' => 'PL',
            'lat' => 52.2297,
            'lng' => 21.0122,
        ]);

        $response = $this->actingAs($this->user)->patchJson("/api/v1/admin/locations/{$location->id}", [
            'name' => 'Updated Name',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'Updated Name');

        $this->assertDatabaseHas('locations', [
            'id' => $location->id,
            'name' => 'Updated Name',
        ]);
    }

    public function test_admin_can_soft_delete_location(): void
    {
        $location = Location::create([
            'name' => 'To Delete',
            'slug' => 'to-delete',
            'status' => 'active',
            'address_line1' => 'Test Address',
            'city' => 'Test City',
            'postal_code' => '00-001',
            'country' => 'PL',
            'lat' => 52.2297,
            'lng' => 21.0122,
        ]);

        $response = $this->actingAs($this->user)->deleteJson("/api/v1/admin/locations/{$location->id}");

        $response->assertStatus(204);

        $this->assertDatabaseHas('locations', [
            'id' => $location->id,
            'status' => 'inactive',
        ]);
    }

    public function test_admin_can_create_pickup_point(): void
    {
        $location = Location::create([
            'name' => 'Test Location',
            'slug' => 'test-location',
            'status' => 'active',
            'address_line1' => 'Test Address',
            'city' => 'Test City',
            'postal_code' => '00-001',
            'country' => 'PL',
            'lat' => 52.2297,
            'lng' => 21.0122,
        ]);

        $response = $this->actingAs($this->user)->postJson("/api/v1/admin/locations/{$location->id}/pickup-points", [
            'name' => 'Main Entrance',
            'address_line1' => 'Test Address',
            'city' => 'Test City',
            'postal_code' => '00-001',
            'lat' => 52.2297,
            'lng' => 21.0122,
            'instructions' => 'Enter through the main door',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Main Entrance')
            ->assertJsonPath('data.instructions', 'Enter through the main door');
    }

    public function test_admin_can_create_delivery_zone(): void
    {
        $location = Location::create([
            'name' => 'Test Location',
            'slug' => 'test-location-zone',
            'status' => 'active',
            'address_line1' => 'Test Address',
            'city' => 'Test City',
            'postal_code' => '00-001',
            'country' => 'PL',
            'lat' => 52.2297,
            'lng' => 21.0122,
        ]);

        $response = $this->actingAs($this->user)->postJson("/api/v1/admin/locations/{$location->id}/delivery-zones", [
            'name' => 'Zone 1',
            'polygon_geojson' => [
                'type' => 'Polygon',
                'coordinates' => [
                    [
                        [21.0000, 52.2200],
                        [21.0300, 52.2200],
                        [21.0300, 52.2400],
                        [21.0000, 52.2400],
                        [21.0000, 52.2200],
                    ],
                ],
            ],
            'priority' => 10,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Zone 1')
            ->assertJsonPath('data.priority', 10);
    }

    public function test_admin_can_create_pricing_rule(): void
    {
        $location = Location::create([
            'name' => 'Test Location',
            'slug' => 'test-location-pricing',
            'status' => 'active',
            'address_line1' => 'Test Address',
            'city' => 'Test City',
            'postal_code' => '00-001',
            'country' => 'PL',
            'lat' => 52.2297,
            'lng' => 21.0122,
        ]);

        $zone = DeliveryZone::create([
            'location_id' => $location->id,
            'name' => 'Zone 1',
            'status' => 'active',
            'polygon_geojson' => [
                'type' => 'Polygon',
                'coordinates' => [
                    [
                        [21.0000, 52.2200],
                        [21.0300, 52.2200],
                        [21.0300, 52.2400],
                        [21.0000, 52.2400],
                        [21.0000, 52.2200],
                    ],
                ],
            ],
            'priority' => 10,
        ]);

        $response = $this->actingAs($this->user)->postJson("/api/v1/admin/delivery-zones/{$zone->id}/pricing-rules", [
            'fee_amount' => 5.00,
            'min_order_amount' => 20.00,
            'free_delivery_threshold' => 80.00,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.fee_amount', '5.00')
            ->assertJsonPath('data.min_order_amount', '20.00')
            ->assertJsonPath('data.free_delivery_threshold', '80.00');
    }

    public function test_admin_can_upsert_lead_time_settings(): void
    {
        $location = Location::create([
            'name' => 'Test Location',
            'slug' => 'test-location-lead',
            'status' => 'active',
            'address_line1' => 'Test Address',
            'city' => 'Test City',
            'postal_code' => '00-001',
            'country' => 'PL',
            'lat' => 52.2297,
            'lng' => 21.0122,
        ]);

        $response = $this->actingAs($this->user)->putJson("/api/v1/admin/locations/{$location->id}/lead-time-settings", [
            'pickup_lead_time_min' => 15,
            'delivery_lead_time_min' => 40,
            'zone_extra_time_min' => 10,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.pickup_lead_time_min', 15)
            ->assertJsonPath('data.delivery_lead_time_min', 40)
            ->assertJsonPath('data.zone_extra_time_min', 10);

        $this->assertDatabaseHas('lead_time_settings', [
            'location_id' => $location->id,
            'pickup_lead_time_min' => 15,
        ]);
    }

    public function test_unauthenticated_user_cannot_access_admin_endpoints(): void
    {
        $response = $this->postJson('/api/v1/admin/locations', [
            'name' => 'New Location',
            'slug' => 'new-location',
            'address_line1' => 'Test Address',
            'city' => 'Test City',
            'postal_code' => '00-001',
            'lat' => 52.2297,
            'lng' => 21.0122,
        ]);

        $response->assertStatus(401);
    }

    public function test_delivery_zone_validation_rejects_invalid_polygon(): void
    {
        $location = Location::create([
            'name' => 'Test Location',
            'slug' => 'test-location-invalid',
            'status' => 'active',
            'address_line1' => 'Test Address',
            'city' => 'Test City',
            'postal_code' => '00-001',
            'country' => 'PL',
            'lat' => 52.2297,
            'lng' => 21.0122,
        ]);

        $response = $this->actingAs($this->user)->postJson("/api/v1/admin/locations/{$location->id}/delivery-zones", [
            'name' => 'Invalid Zone',
            'polygon_geojson' => [
                'type' => 'Polygon',
                'coordinates' => [
                    [
                        [21.0000, 52.2200],
                        [21.0300, 52.2200],
                        [21.0000, 52.2200],
                    ],
                ],
            ],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['polygon_geojson']);
    }

    public function test_pricing_rule_validation_rejects_invalid_threshold(): void
    {
        $location = Location::create([
            'name' => 'Test Location',
            'slug' => 'test-location-threshold',
            'status' => 'active',
            'address_line1' => 'Test Address',
            'city' => 'Test City',
            'postal_code' => '00-001',
            'country' => 'PL',
            'lat' => 52.2297,
            'lng' => 21.0122,
        ]);

        $zone = DeliveryZone::create([
            'location_id' => $location->id,
            'name' => 'Zone 1',
            'status' => 'active',
            'polygon_geojson' => [
                'type' => 'Polygon',
                'coordinates' => [
                    [
                        [21.0000, 52.2200],
                        [21.0300, 52.2200],
                        [21.0300, 52.2400],
                        [21.0000, 52.2400],
                        [21.0000, 52.2200],
                    ],
                ],
            ],
            'priority' => 10,
        ]);

        $response = $this->actingAs($this->user)->postJson("/api/v1/admin/delivery-zones/{$zone->id}/pricing-rules", [
            'fee_amount' => 5.00,
            'min_order_amount' => 50.00,
            'free_delivery_threshold' => 30.00,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['free_delivery_threshold']);
    }
}
