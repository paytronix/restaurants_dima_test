<?php

namespace Tests\Unit\Services;

use App\Models\DeliveryPricingRule;
use App\Models\DeliveryZone;
use App\Models\LeadTimeSetting;
use App\Models\Location;
use App\Services\DeliveryPricingService;
use App\Services\LeadTimeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeliveryPricingServiceTest extends TestCase
{
    use RefreshDatabase;

    private DeliveryPricingService $service;

    private Location $location;

    private DeliveryZone $zone;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new DeliveryPricingService(new LeadTimeService);

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

        $this->zone = DeliveryZone::create([
            'location_id' => $this->location->id,
            'name' => 'Test Zone',
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
            'priority' => 0,
        ]);
    }

    public function test_quote_returns_zero_fee_when_no_pricing_rule(): void
    {
        $quote = $this->service->quote($this->location->id, $this->zone->id, '50.00');

        $this->assertTrue($quote->serviceable);
        $this->assertEquals('0.00', $quote->deliveryFee);
        $this->assertEquals('0.00', $quote->minOrderAmount);
        $this->assertNull($quote->freeDeliveryThreshold);
    }

    public function test_quote_returns_fee_from_pricing_rule(): void
    {
        DeliveryPricingRule::create([
            'delivery_zone_id' => $this->zone->id,
            'fee_amount' => 7.50,
            'min_order_amount' => 20.00,
            'free_delivery_threshold' => 80.00,
            'currency' => 'PLN',
        ]);

        $quote = $this->service->quote($this->location->id, $this->zone->id, '50.00');

        $this->assertTrue($quote->serviceable);
        $this->assertEquals('7.50', $quote->deliveryFee);
        $this->assertEquals('20.00', $quote->minOrderAmount);
        $this->assertEquals('80.00', $quote->freeDeliveryThreshold);
        $this->assertEquals('PLN', $quote->currency);
    }

    public function test_quote_applies_free_delivery_threshold(): void
    {
        DeliveryPricingRule::create([
            'delivery_zone_id' => $this->zone->id,
            'fee_amount' => 7.50,
            'min_order_amount' => 20.00,
            'free_delivery_threshold' => 80.00,
            'currency' => 'PLN',
        ]);

        $quote = $this->service->quote($this->location->id, $this->zone->id, '100.00');

        $this->assertTrue($quote->serviceable);
        $this->assertEquals('0.00', $quote->deliveryFee);
    }

    public function test_quote_checks_minimum_order_amount(): void
    {
        DeliveryPricingRule::create([
            'delivery_zone_id' => $this->zone->id,
            'fee_amount' => 7.50,
            'min_order_amount' => 30.00,
            'free_delivery_threshold' => null,
            'currency' => 'PLN',
        ]);

        $quoteBelowMin = $this->service->quote($this->location->id, $this->zone->id, '20.00');
        $quoteAboveMin = $this->service->quote($this->location->id, $this->zone->id, '50.00');

        $this->assertFalse($quoteBelowMin->meetsMinimumOrder);
        $this->assertTrue($quoteAboveMin->meetsMinimumOrder);
    }

    public function test_quote_includes_estimated_delivery_time(): void
    {
        LeadTimeSetting::create([
            'location_id' => $this->location->id,
            'pickup_lead_time_min' => 15,
            'delivery_lead_time_min' => 40,
            'zone_extra_time_min' => 10,
        ]);

        $quote = $this->service->quote($this->location->id, $this->zone->id, '50.00');

        $this->assertEquals(50, $quote->estimatedDeliveryMinutes);
    }

    public function test_not_serviceable_quote_returns_correct_structure(): void
    {
        $quote = $this->service->notServiceableQuote();

        $this->assertFalse($quote->serviceable);
        $this->assertNull($quote->deliveryZoneId);
        $this->assertNull($quote->deliveryFee);
        $this->assertNull($quote->minOrderAmount);
        $this->assertNull($quote->freeDeliveryThreshold);
        $this->assertNull($quote->estimatedDeliveryMinutes);
    }
}
