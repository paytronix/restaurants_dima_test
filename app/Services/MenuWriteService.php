<?php

namespace App\Services;

use App\Models\Category;
use App\Models\MenuItem;
use App\Models\Modifier;
use App\Models\ModifierOption;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class MenuWriteService
{
    public function createCategory(array $data): Category
    {
        $category = Category::create($data);
        $this->invalidateCatalogCache();

        return $category;
    }

    public function updateCategory(int $id, array $data): Category
    {
        $category = Category::findOrFail($id);
        $category->update($data);
        $this->invalidateCatalogCache();

        return $category;
    }

    public function deleteCategory(int $id, bool $force = false): void
    {
        $category = Category::findOrFail($id);

        if (! $force) {
            $hasActiveItems = $category->menuItems()
                ->where('is_active', true)
                ->exists();

            if ($hasActiveItems) {
                throw new ConflictHttpException('Cannot delete category with active items. Use force=true to override.');
            }
        }

        $category->delete();
        $this->invalidateCatalogCache();
    }

    public function createMenuItem(array $data): MenuItem
    {
        $item = MenuItem::create($data);
        $this->invalidateCatalogCache();

        return $item;
    }

    public function updateMenuItem(int $id, array $data): MenuItem
    {
        $item = MenuItem::findOrFail($id);
        $item->update($data);
        $this->invalidateCatalogCache();

        return $item;
    }

    public function deleteMenuItem(int $id): void
    {
        $item = MenuItem::findOrFail($id);
        $item->delete();
        $this->invalidateCatalogCache();
    }

    public function createModifier(array $data): Modifier
    {
        $modifier = Modifier::create($data);
        $this->invalidateCatalogCache();

        return $modifier;
    }

    public function updateModifier(int $id, array $data): Modifier
    {
        $modifier = Modifier::findOrFail($id);
        $modifier->update($data);
        $this->invalidateCatalogCache();

        return $modifier;
    }

    public function deleteModifier(int $id): void
    {
        $modifier = Modifier::findOrFail($id);
        $modifier->delete();
        $this->invalidateCatalogCache();
    }

    public function createModifierOption(int $modifierId, array $data): ModifierOption
    {
        $data['modifier_id'] = $modifierId;
        $option = ModifierOption::create($data);
        $this->invalidateCatalogCache();

        return $option;
    }

    public function updateModifierOption(int $modifierId, int $optionId, array $data): ModifierOption
    {
        $option = ModifierOption::where('modifier_id', $modifierId)
            ->where('id', $optionId)
            ->firstOrFail();
        $option->update($data);
        $this->invalidateCatalogCache();

        return $option;
    }

    public function deleteModifierOption(int $modifierId, int $optionId): void
    {
        $option = ModifierOption::where('modifier_id', $modifierId)
            ->where('id', $optionId)
            ->firstOrFail();
        $option->delete();
        $this->invalidateCatalogCache();
    }

    public function attachModifierToItem(int $itemId, int $modifierId): void
    {
        $item = MenuItem::findOrFail($itemId);
        $modifier = Modifier::findOrFail($modifierId);

        if (! $item->modifiers()->where('modifier_id', $modifierId)->exists()) {
            $item->modifiers()->attach($modifierId);
            $this->invalidateCatalogCache();
        }
    }

    public function detachModifierFromItem(int $itemId, int $modifierId): void
    {
        $item = MenuItem::findOrFail($itemId);
        $item->modifiers()->detach($modifierId);
        $this->invalidateCatalogCache();
    }

    private function invalidateCatalogCache(): void
    {
        Cache::forget('catalog:v1');
        Cache::forget('catalog:v1:inactive');
    }
}
