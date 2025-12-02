<?php

namespace Tests\Feature\Api\V1;

use App\Models\Category;
use App\Models\ItemAvailability;
use App\Models\MenuItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_returns_successful_response(): void
    {
        $response = $this->getJson('/api/v1/catalog');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'categories',
                    'items',
                ],
                'meta' => [
                    'generated_at',
                ],
            ]);
    }

    public function test_catalog_includes_cache_headers(): void
    {
        $response = $this->getJson('/api/v1/catalog');

        $response->assertStatus(200);

        $cacheControl = $response->headers->get('Cache-Control');
        $this->assertStringContainsString('public', $cacheControl);
        $this->assertStringContainsString('max-age=60', $cacheControl);

        $response->assertHeader('ETag')
            ->assertHeader('Last-Modified');
    }

    public function test_catalog_returns_available_items_with_is_available_now_flag(): void
    {
        $category = Category::create([
            'name' => 'Test Category',
            'position' => 1,
            'is_active' => true,
        ]);

        $item = MenuItem::create([
            'category_id' => $category->id,
            'name' => 'Test Item',
            'price' => '25.00',
            'currency' => 'PLN',
            'is_active' => true,
        ]);

        ItemAvailability::create([
            'menu_item_id' => $item->id,
            'day_of_week' => now()->dayOfWeek,
            'time_from' => '00:00',
            'time_to' => '23:59',
        ]);

        $response = $this->getJson('/api/v1/catalog');

        $response->assertStatus(200)
            ->assertJsonPath('data.items.0.is_available_now', true);
    }

    public function test_catalog_respects_cache(): void
    {
        $response1 = $this->getJson('/api/v1/catalog');
        $etag1 = $response1->headers->get('ETag');

        $response2 = $this->getJson('/api/v1/catalog');
        $etag2 = $response2->headers->get('ETag');

        $this->assertEquals($etag1, $etag2);
    }
}
