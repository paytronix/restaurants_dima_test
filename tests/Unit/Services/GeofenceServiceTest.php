<?php

namespace Tests\Unit\Services;

use App\Models\DeliveryZone;
use App\Models\Location;
use App\Services\GeofenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GeofenceServiceTest extends TestCase
{
    use RefreshDatabase;

    private GeofenceService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new GeofenceService;
    }

    public function test_point_inside_polygon_returns_true(): void
    {
        $polygon = [
            [21.0000, 52.2200],
            [21.0300, 52.2200],
            [21.0300, 52.2400],
            [21.0000, 52.2400],
            [21.0000, 52.2200],
        ];

        $result = $this->service->isPointInPolygon(52.2300, 21.0150, $polygon);

        $this->assertTrue($result);
    }

    public function test_point_outside_polygon_returns_false(): void
    {
        $polygon = [
            [21.0000, 52.2200],
            [21.0300, 52.2200],
            [21.0300, 52.2400],
            [21.0000, 52.2400],
            [21.0000, 52.2200],
        ];

        $result = $this->service->isPointInPolygon(52.1000, 21.0150, $polygon);

        $this->assertFalse($result);
    }

    public function test_point_on_boundary_is_inside(): void
    {
        $polygon = [
            [21.0000, 52.2200],
            [21.0300, 52.2200],
            [21.0300, 52.2400],
            [21.0000, 52.2400],
            [21.0000, 52.2200],
        ];

        $result = $this->service->isPointInPolygon(52.2200, 21.0150, $polygon);

        $this->assertTrue($result);
    }

    public function test_find_matching_zone_returns_highest_priority_zone(): void
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

        $lowPriorityZone = DeliveryZone::create([
            'location_id' => $location->id,
            'name' => 'Low Priority Zone',
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

        $highPriorityZone = DeliveryZone::create([
            'location_id' => $location->id,
            'name' => 'High Priority Zone',
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

        $result = $this->service->findMatchingZone($location->id, 52.2300, 21.0150);

        $this->assertNotNull($result);
        $this->assertEquals($highPriorityZone->id, $result->id);
    }

    public function test_find_matching_zone_returns_null_when_no_zone_matches(): void
    {
        $location = Location::create([
            'name' => 'Test Location',
            'slug' => 'test-location-2',
            'status' => 'active',
            'address_line1' => 'Test Address',
            'city' => 'Test City',
            'postal_code' => '00-001',
            'country' => 'PL',
            'lat' => 52.2297,
            'lng' => 21.0122,
        ]);

        DeliveryZone::create([
            'location_id' => $location->id,
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

        $result = $this->service->findMatchingZone($location->id, 50.0000, 19.0000);

        $this->assertNull($result);
    }

    public function test_validate_polygon_geojson_returns_empty_for_valid_polygon(): void
    {
        $geojson = [
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
        ];

        $errors = $this->service->validatePolygonGeojson($geojson);

        $this->assertEmpty($errors);
    }

    public function test_validate_polygon_geojson_returns_error_for_missing_type(): void
    {
        $geojson = [
            'coordinates' => [
                [
                    [21.0000, 52.2200],
                    [21.0300, 52.2200],
                    [21.0300, 52.2400],
                    [21.0000, 52.2400],
                    [21.0000, 52.2200],
                ],
            ],
        ];

        $errors = $this->service->validatePolygonGeojson($geojson);

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('type', $errors[0]);
    }

    public function test_validate_polygon_geojson_returns_error_for_unclosed_ring(): void
    {
        $geojson = [
            'type' => 'Polygon',
            'coordinates' => [
                [
                    [21.0000, 52.2200],
                    [21.0300, 52.2200],
                    [21.0300, 52.2400],
                    [21.0000, 52.2400],
                ],
            ],
        ];

        $errors = $this->service->validatePolygonGeojson($geojson);

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('closed', $errors[0]);
    }

    public function test_validate_polygon_geojson_returns_error_for_too_few_points(): void
    {
        $geojson = [
            'type' => 'Polygon',
            'coordinates' => [
                [
                    [21.0000, 52.2200],
                    [21.0300, 52.2200],
                    [21.0000, 52.2200],
                ],
            ],
        ];

        $errors = $this->service->validatePolygonGeojson($geojson);

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('4 points', $errors[0]);
    }
}
