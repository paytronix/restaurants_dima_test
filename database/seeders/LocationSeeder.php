<?php

namespace Database\Seeders;

use App\Models\DeliveryPricingRule;
use App\Models\DeliveryZone;
use App\Models\LeadTimeSetting;
use App\Models\Location;
use App\Models\PickupPoint;
use Illuminate\Database\Seeder;

class LocationSeeder extends Seeder
{
    public function run(): void
    {
        $location = Location::create([
            'name' => 'Warsaw Central',
            'slug' => 'warsaw-central',
            'status' => 'active',
            'phone' => '+48 22 123 4567',
            'email' => 'warsaw@example.com',
            'address_line1' => 'ul. Marszalkowska 100',
            'address_line2' => null,
            'city' => 'Warsaw',
            'postal_code' => '00-001',
            'country' => 'PL',
            'lat' => 52.2297,
            'lng' => 21.0122,
        ]);

        PickupPoint::create([
            'location_id' => $location->id,
            'name' => 'Main Entrance',
            'status' => 'active',
            'address_line1' => 'ul. Marszalkowska 100',
            'address_line2' => 'Ground Floor',
            'city' => 'Warsaw',
            'postal_code' => '00-001',
            'country' => 'PL',
            'lat' => 52.2297,
            'lng' => 21.0122,
            'instructions' => 'Enter through the main door and proceed to the pickup counter on the left.',
        ]);

        PickupPoint::create([
            'location_id' => $location->id,
            'name' => 'Side Entrance',
            'status' => 'active',
            'address_line1' => 'ul. Marszalkowska 100',
            'address_line2' => 'Side Street',
            'city' => 'Warsaw',
            'postal_code' => '00-001',
            'country' => 'PL',
            'lat' => 52.2298,
            'lng' => 21.0125,
            'instructions' => 'Use the side entrance for faster pickup during busy hours.',
        ]);

        $zone1 = DeliveryZone::create([
            'location_id' => $location->id,
            'name' => 'Zone 1 - City Center',
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
            'delivery_zone_id' => $zone1->id,
            'fee_amount' => 5.00,
            'min_order_amount' => 20.00,
            'free_delivery_threshold' => 80.00,
            'currency' => 'PLN',
        ]);

        $zone2 = DeliveryZone::create([
            'location_id' => $location->id,
            'name' => 'Zone 2 - Extended Area',
            'status' => 'active',
            'polygon_geojson' => [
                'type' => 'Polygon',
                'coordinates' => [
                    [
                        [20.9500, 52.1800],
                        [21.0800, 52.1800],
                        [21.0800, 52.2800],
                        [20.9500, 52.2800],
                        [20.9500, 52.1800],
                    ],
                ],
            ],
            'priority' => 5,
        ]);

        DeliveryPricingRule::create([
            'delivery_zone_id' => $zone2->id,
            'fee_amount' => 10.00,
            'min_order_amount' => 30.00,
            'free_delivery_threshold' => 120.00,
            'currency' => 'PLN',
        ]);

        LeadTimeSetting::create([
            'location_id' => $location->id,
            'pickup_lead_time_min' => 15,
            'delivery_lead_time_min' => 40,
            'zone_extra_time_min' => 10,
        ]);
    }
}
