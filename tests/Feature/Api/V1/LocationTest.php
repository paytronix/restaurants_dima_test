<?php

namespace Tests\Feature\Api\V1;

use App\Models\DeliveryPricingRule;
use App\Models\DeliveryZone;
use App\Models\LeadTimeSetting;
use App\Models\Location;
use App\Models\PickupPoint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocationTest extends TestCase
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
        ]);
    }

    public function test_list_locations_returns_active_locations(): void
    {
        Location::create([
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

        $response = $this->getJson('/api/v1/locations');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Warsaw Central')
            ->assertJsonPath('meta.total', 1);
    }

    public function test_show_location_returns_location_details(): void
    {
        PickupPoint::create([
            'location_id' => $this->location->id,
            'name' => 'Main Entrance',
            'status' => 'active',
            'address_line1' => 'ul. Marszalkowska 100',
            'city' => 'Warsaw',
            'postal_code' => '00-001',
            'country' => 'PL',
            'lat' => 52.2297,
            'lng' => 21.0122,
        ]);

        $response = $this->getJson("/api/v1/locations/{$this->location->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'Warsaw Central')
            ->assertJsonPath('data.slug', 'warsaw-central')
            ->assertJsonPath('data.pickup_points_count', 1);
    }

    public function test_show_inactive_location_returns_404(): void
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

        $response = $this->getJson("/api/v1/locations/{$inactiveLocation->id}");

        $response->assertStatus(404);
    }

    public function test_list_pickup_points_returns_active_pickup_points(): void
    {
        PickupPoint::create([
            'location_id' => $this->location->id,
            'name' => 'Main Entrance',
            'status' => 'active',
            'address_line1' => 'ul. Marszalkowska 100',
            'city' => 'Warsaw',
            'postal_code' => '00-001',
            'country' => 'PL',
            'lat' => 52.2297,
            'lng' => 21.0122,
        ]);

        PickupPoint::create([
            'location_id' => $this->location->id,
            'name' => 'Inactive Point',
            'status' => 'inactive',
            'address_line1' => 'ul. Marszalkowska 100',
            'city' => 'Warsaw',
            'postal_code' => '00-001',
            'country' => 'PL',
            'lat' => 52.2297,
            'lng' => 21.0122,
        ]);

        $response = $this->getJson("/api/v1/locations/{$this->location->id}/pickup-points");

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Main Entrance')
            ->assertJsonPath('meta.total', 1);
    }

    public function test_delivery_quote_returns_serviceable_for_point_in_zone(): void
    {
        $zone = DeliveryZone::create([
            'location_id' => $this->location->id,
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

        DeliveryPricingRule::create([
            'delivery_zone_id' => $zone->id,
            'fee_amount' => 5.00,
            'min_order_amount' => 20.00,
            'free_delivery_threshold' => 80.00,
            'currency' => 'PLN',
        ]);

        LeadTimeSetting::create([
            'location_id' => $this->location->id,
            'pickup_lead_time_min' => 15,
            'delivery_lead_time_min' => 40,
            'zone_extra_time_min' => 10,
        ]);

        $response = $this->postJson("/api/v1/locations/{$this->location->id}/delivery/quote", [
            'lat' => 52.2300,
            'lng' => 21.0150,
            'order_subtotal' => '50.00',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.serviceable', true)
            ->assertJsonPath('data.delivery_zone_id', $zone->id)
            ->assertJsonPath('data.delivery_fee', '5.00')
            ->assertJsonPath('data.min_order_amount', '20.00')
            ->assertJsonPath('data.free_delivery_threshold', '80.00')
            ->assertJsonPath('data.currency', 'PLN')
            ->assertJsonPath('data.estimated_delivery_minutes', 50);
    }

    public function test_delivery_quote_returns_not_serviceable_for_point_outside_zone(): void
    {
        DeliveryZone::create([
            'location_id' => $this->location->id,
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

        $response = $this->postJson("/api/v1/locations/{$this->location->id}/delivery/quote", [
            'lat' => 50.0000,
            'lng' => 19.0000,
            'order_subtotal' => '50.00',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.serviceable', false)
            ->assertJsonPath('data.delivery_zone_id', null)
            ->assertJsonPath('data.delivery_fee', null);
    }

    public function test_delivery_quote_applies_free_delivery_threshold(): void
    {
        $zone = DeliveryZone::create([
            'location_id' => $this->location->id,
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

        DeliveryPricingRule::create([
            'delivery_zone_id' => $zone->id,
            'fee_amount' => 5.00,
            'min_order_amount' => 20.00,
            'free_delivery_threshold' => 80.00,
            'currency' => 'PLN',
        ]);

        $response = $this->postJson("/api/v1/locations/{$this->location->id}/delivery/quote", [
            'lat' => 52.2300,
            'lng' => 21.0150,
            'order_subtotal' => '100.00',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.serviceable', true)
            ->assertJsonPath('data.delivery_fee', '0.00');
    }

    public function test_delivery_quote_validates_required_fields(): void
    {
        $response = $this->postJson("/api/v1/locations/{$this->location->id}/delivery/quote", []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['lat', 'lng', 'order_subtotal']);
    }

    public function test_delivery_quote_validates_lat_lng_range(): void
    {
        $response = $this->postJson("/api/v1/locations/{$this->location->id}/delivery/quote", [
            'lat' => 100.0,
            'lng' => 200.0,
            'order_subtotal' => '50.00',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['lat', 'lng']);
    }
}
