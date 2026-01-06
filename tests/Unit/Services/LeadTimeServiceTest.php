<?php

namespace Tests\Unit\Services;

use App\Models\LeadTimeSetting;
use App\Models\Location;
use App\Services\LeadTimeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeadTimeServiceTest extends TestCase
{
    use RefreshDatabase;

    private LeadTimeService $service;

    private Location $location;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new LeadTimeService;

        $this->location = Location::create([
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
    }

    public function test_estimate_pickup_returns_default_when_no_settings(): void
    {
        $result = $this->service->estimatePickup($this->location->id);

        $this->assertEquals(20, $result);
    }

    public function test_estimate_pickup_returns_configured_value(): void
    {
        LeadTimeSetting::create([
            'location_id' => $this->location->id,
            'pickup_lead_time_min' => 15,
            'delivery_lead_time_min' => 40,
            'zone_extra_time_min' => 10,
        ]);

        $result = $this->service->estimatePickup($this->location->id);

        $this->assertEquals(15, $result);
    }

    public function test_estimate_delivery_returns_default_when_no_settings(): void
    {
        $result = $this->service->estimateDelivery($this->location->id);

        $this->assertEquals(45, $result);
    }

    public function test_estimate_delivery_returns_base_plus_extra_time(): void
    {
        LeadTimeSetting::create([
            'location_id' => $this->location->id,
            'pickup_lead_time_min' => 15,
            'delivery_lead_time_min' => 40,
            'zone_extra_time_min' => 10,
        ]);

        $result = $this->service->estimateDelivery($this->location->id);

        $this->assertEquals(50, $result);
    }

    public function test_estimate_delivery_with_zero_extra_time(): void
    {
        LeadTimeSetting::create([
            'location_id' => $this->location->id,
            'pickup_lead_time_min' => 15,
            'delivery_lead_time_min' => 35,
            'zone_extra_time_min' => 0,
        ]);

        $result = $this->service->estimateDelivery($this->location->id);

        $this->assertEquals(35, $result);
    }
}
