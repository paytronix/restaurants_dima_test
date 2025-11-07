<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\CatalogResource;
use App\Services\CatalogReadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class CatalogController extends Controller
{
    public function __construct(
        private CatalogReadService $catalogService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $includeInactive = $request->boolean('include_inactive', false);
        $now = $request->query('now');

        $cacheKey = 'catalog:v1'.($includeInactive ? ':inactive' : '');

        $catalog = Cache::remember($cacheKey, 60, function () use ($now, $includeInactive) {
            return $this->catalogService->getCatalog($now, $includeInactive);
        });

        $response = response()->json(new CatalogResource($catalog));

        $etag = md5(json_encode($catalog));
        $response->header('Cache-Control', 'public, max-age=60');
        $response->header('ETag', '"'.$etag.'"');
        $response->header('Last-Modified', now()->toRfc7231String());

        return $response;
    }
}
