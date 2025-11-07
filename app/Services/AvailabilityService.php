<?php

namespace App\Services;

use App\Models\ItemAvailability;
use App\Models\ItemSoldout;
use App\Models\MenuItem;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class AvailabilityService
{
    public function addAvailabilityWindow(int $menuItemId, array $data): ItemAvailability
    {
        MenuItem::findOrFail($menuItemId);

        if ($data['time_from'] >= $data['time_to']) {
            throw new UnprocessableEntityHttpException('time_from must be less than time_to');
        }

        if ($data['day_of_week'] < 0 || $data['day_of_week'] > 6) {
            throw new UnprocessableEntityHttpException('day_of_week must be between 0 and 6');
        }

        $data['menu_item_id'] = $menuItemId;
        $availability = ItemAvailability::create($data);

        $this->invalidateCatalogCache();

        return $availability;
    }

    public function deleteAvailabilityWindow(int $menuItemId, int $availabilityId): void
    {
        $availability = ItemAvailability::where('menu_item_id', $menuItemId)
            ->where('id', $availabilityId)
            ->firstOrFail();

        $availability->delete();
        $this->invalidateCatalogCache();
    }

    public function markSoldOut(int $menuItemId, string $date, ?string $reason = null): ItemSoldout
    {
        MenuItem::findOrFail($menuItemId);

        $soldout = ItemSoldout::updateOrCreate(
            [
                'menu_item_id' => $menuItemId,
                'date' => $date,
            ],
            [
                'reason' => $reason,
            ]
        );

        $this->invalidateCatalogCache();

        return $soldout;
    }

    public function removeSoldOut(int $menuItemId, int $soldoutId): void
    {
        $soldout = ItemSoldout::where('menu_item_id', $menuItemId)
            ->where('id', $soldoutId)
            ->firstOrFail();

        $soldout->delete();
        $this->invalidateCatalogCache();
    }

    private function invalidateCatalogCache(): void
    {
        Cache::forget('catalog:v1');
        Cache::forget('catalog:v1:inactive');
    }
}
