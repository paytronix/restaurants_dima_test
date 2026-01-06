<?php

namespace App\Services;

use App\Models\DeliveryZone;

class GeofenceService
{
    public function findMatchingZone(int $locationId, float $lat, float $lng): ?DeliveryZone
    {
        $zones = DeliveryZone::where('location_id', $locationId)
            ->where('status', DeliveryZone::STATUS_ACTIVE)
            ->orderByDesc('priority')
            ->orderBy('id')
            ->get();

        if ($zones->isEmpty()) {
            return null;
        }

        foreach ($zones as $zone) {
            $polygon = $zone->getPolygonCoordinates();

            if (empty($polygon)) {
                continue;
            }

            if ($this->isPointInPolygon($lat, $lng, $polygon)) {
                return $zone;
            }
        }

        return null;
    }

    public function isPointInPolygon(float $lat, float $lng, array $polygon): bool
    {
        $vertices = count($polygon);

        if ($vertices < 3) {
            return false;
        }

        $inside = false;

        for ($i = 0, $j = $vertices - 1; $i < $vertices; $j = $i++) {
            $xi = (float) $polygon[$i][0];
            $yi = (float) $polygon[$i][1];
            $xj = (float) $polygon[$j][0];
            $yj = (float) $polygon[$j][1];

            $intersect = (($yi > $lat) !== ($yj > $lat))
                && ($lng < ($xj - $xi) * ($lat - $yi) / ($yj - $yi) + $xi);

            if ($intersect) {
                $inside = ! $inside;
            }
        }

        return $inside;
    }

    public function validatePolygonGeojson(array $geojson): array
    {
        $errors = [];

        if (! isset($geojson['type'])) {
            $errors[] = 'The polygon_geojson must have a "type" field.';

            return $errors;
        }

        if ($geojson['type'] !== 'Polygon') {
            $errors[] = 'The polygon_geojson type must be "Polygon".';

            return $errors;
        }

        if (! isset($geojson['coordinates']) || ! is_array($geojson['coordinates'])) {
            $errors[] = 'The polygon_geojson must have a "coordinates" array.';

            return $errors;
        }

        if (empty($geojson['coordinates'][0]) || ! is_array($geojson['coordinates'][0])) {
            $errors[] = 'The polygon_geojson coordinates must contain at least one ring.';

            return $errors;
        }

        $ring = $geojson['coordinates'][0];

        if (count($ring) < 4) {
            $errors[] = 'The polygon must have at least 4 points (3 distinct points + closure point).';

            return $errors;
        }

        $first = $ring[0];
        $last = $ring[count($ring) - 1];

        if ($first[0] !== $last[0] || $first[1] !== $last[1]) {
            $errors[] = 'The polygon ring must be closed (first and last points must be identical).';

            return $errors;
        }

        foreach ($ring as $index => $point) {
            if (! is_array($point) || count($point) < 2) {
                $errors[] = "Point at index {$index} must be an array with at least 2 elements [lng, lat].";

                continue;
            }

            $pointLng = $point[0];
            $pointLat = $point[1];

            if (! is_numeric($pointLng) || $pointLng < -180 || $pointLng > 180) {
                $errors[] = "Point at index {$index} has invalid longitude (must be between -180 and 180).";
            }

            if (! is_numeric($pointLat) || $pointLat < -90 || $pointLat > 90) {
                $errors[] = "Point at index {$index} has invalid latitude (must be between -90 and 90).";
            }
        }

        return $errors;
    }
}
