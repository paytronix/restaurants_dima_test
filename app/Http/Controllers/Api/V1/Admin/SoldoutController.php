<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSoldoutRequest;
use App\Models\ItemSoldout;
use App\Models\MenuItem;
use App\Services\AvailabilityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class SoldoutController extends Controller
{
    public function __construct(
        private AvailabilityService $availabilityService
    ) {}

    public function store(StoreSoldoutRequest $request, MenuItem $item): JsonResponse
    {
        $soldout = $this->availabilityService->markSoldOut(
            $item->id,
            $request->input('date'),
            $request->input('reason')
        );

        return response()->json([
            'data' => [
                'id' => $soldout->id,
                'menu_item_id' => $soldout->menu_item_id,
                'date' => $soldout->date,
                'reason' => $soldout->reason,
            ],
        ], 201);
    }

    public function destroy(MenuItem $item, ItemSoldout $soldout): Response
    {
        $this->availabilityService->removeSoldout($soldout->id);

        return response()->noContent();
    }
}
