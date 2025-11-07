<?php

namespace App\Services;

use App\Models\Category;
use App\Models\MenuItem;
use Carbon\Carbon;
use Carbon\CarbonInterface;

class CatalogReadService
{
    public function getCatalog(?string $nowRfc3339 = null, bool $includeInactive = false): array
    {
        $now = $nowRfc3339 ? Carbon::parse($nowRfc3339) : now();

        $categoriesQuery = Category::query()
            ->orderBy('position')
            ->orderBy('id');

        if (! $includeInactive) {
            $categoriesQuery->where('is_active', true);
        }

        $categories = $categoriesQuery->get();

        $itemsQuery = MenuItem::query()
            ->with(['modifiers.options' => function ($query) {
                $query->where('is_active', true)->orderBy('position')->orderBy('id');
            }])
            ->orderBy('category_id')
            ->orderBy('id');

        if (! $includeInactive) {
            $itemsQuery->where('is_active', true);
        }

        $items = $itemsQuery->get()->map(function (MenuItem $item) use ($now) {
            $itemArray = $item->toArray();
            $itemArray['is_available_now'] = $this->isItemAvailableNow($item, $now);
            $itemArray['modifiers'] = $item->modifiers->map(function ($modifier) {
                $modifierArray = $modifier->toArray();
                $modifierArray['options'] = $modifier->options->toArray();

                return $modifierArray;
            })->toArray();

            return $itemArray;
        })->toArray();

        return [
            'categories' => $categories->toArray(),
            'items' => $items,
        ];
    }

    public function isItemAvailableNow(MenuItem $item, CarbonInterface $now): bool
    {
        if (! $item->is_active) {
            return false;
        }

        $today = $now->toDateString();
        $soldoutExists = $item->soldouts()
            ->where('date', $today)
            ->exists();

        if ($soldoutExists) {
            return false;
        }

        $availabilities = $item->availabilities;

        if ($availabilities->isEmpty()) {
            return true;
        }

        $dayOfWeek = $now->dayOfWeek;
        $currentTime = $now->format('H:i:s');

        $hasMatchingAvailability = $availabilities->some(function ($availability) use ($dayOfWeek, $currentTime) {
            if ($availability->day_of_week !== $dayOfWeek) {
                return false;
            }

            $timeFrom = $availability->time_from;
            $timeTo = $availability->time_to;

            return $currentTime >= $timeFrom && $currentTime <= $timeTo;
        });

        return $hasMatchingAvailability;
    }
}
