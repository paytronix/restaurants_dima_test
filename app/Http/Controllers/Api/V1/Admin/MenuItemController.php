<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMenuItemRequest;
use App\Http\Requests\UpdateMenuItemRequest;
use App\Http\Resources\MenuItemResource;
use App\Models\MenuItem;
use App\Models\Modifier;
use App\Services\MenuWriteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class MenuItemController extends Controller
{
    public function __construct(
        private MenuWriteService $menuService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = MenuItem::with(['category', 'modifiers.options']);

        if ($request->has('category_id')) {
            $query->where('category_id', $request->input('category_id'));
        }

        if ($request->has('q')) {
            $query->where('name', 'like', '%'.$request->input('q').'%');
        }

        if ($request->has('active')) {
            $query->where('is_active', $request->boolean('active'));
        }

        $items = $query->get();

        return response()->json([
            'data' => MenuItemResource::collection($items),
            'meta' => [
                'total' => $items->count(),
            ],
        ]);
    }

    public function store(StoreMenuItemRequest $request): JsonResponse
    {
        $item = $this->menuService->createMenuItem($request->validated());

        return response()->json([
            'data' => new MenuItemResource($item->load(['category', 'modifiers.options'])),
        ], 201);
    }

    public function show(MenuItem $item): JsonResponse
    {
        return response()->json([
            'data' => new MenuItemResource($item->load(['category', 'modifiers.options'])),
        ]);
    }

    public function update(UpdateMenuItemRequest $request, MenuItem $item): JsonResponse
    {
        $item = $this->menuService->updateMenuItem($item->id, $request->validated());

        return response()->json([
            'data' => new MenuItemResource($item->load(['category', 'modifiers.options'])),
        ]);
    }

    public function destroy(MenuItem $item): Response
    {
        $this->menuService->deleteMenuItem($item->id);

        return response()->noContent();
    }

    public function attachModifier(MenuItem $item, Modifier $modifier): JsonResponse
    {
        $this->menuService->attachModifier($item->id, $modifier->id);

        return response()->json([
            'data' => new MenuItemResource($item->load(['category', 'modifiers.options'])),
        ]);
    }

    public function detachModifier(MenuItem $item, Modifier $modifier): Response
    {
        $this->menuService->detachModifier($item->id, $modifier->id);

        return response()->noContent();
    }
}
