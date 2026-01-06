<?php

namespace App\Http\Requests\DeliveryZone;

use App\Services\GeofenceService;
use Illuminate\Foundation\Http\FormRequest;

class UpdateDeliveryZoneRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'sometimes|string|max:255',
            'status' => 'sometimes|string|in:active,inactive',
            'polygon_geojson' => 'sometimes|array',
            'polygon_geojson.type' => 'required_with:polygon_geojson|string|in:Polygon',
            'polygon_geojson.coordinates' => 'required_with:polygon_geojson|array|min:1',
            'polygon_geojson.coordinates.0' => 'required_with:polygon_geojson|array|min:4',
            'priority' => 'sometimes|integer|min:0|max:1000',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $polygonGeojson = $this->input('polygon_geojson');

            if (! is_array($polygonGeojson)) {
                return;
            }

            $geofenceService = app(GeofenceService::class);
            $errors = $geofenceService->validatePolygonGeojson($polygonGeojson);

            foreach ($errors as $error) {
                $validator->errors()->add('polygon_geojson', $error);
            }
        });
    }
}
