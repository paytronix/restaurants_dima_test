<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreModifierRequest;
use App\Http\Requests\UpdateModifierRequest;
use App\Http\Resources\ModifierResource;
use App\Models\Modifier;
use App\Services\MenuWriteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class ModifierController extends Controller
{
    public function __construct(
        private MenuWriteService $menuService
    ) {}

    public function index(): JsonResponse
    {
        $modifiers = Modifier::with('options')->get();

        return response()->json([
            'data' => ModifierResource::collection($modifiers),
            'meta' => [
                'total' => $modifiers->count(),
            ],
        ]);
    }

    public function store(StoreModifierRequest $request): JsonResponse
    {
        $modifier = $this->menuService->createModifier($request->validated());

        return response()->json([
            'data' => new ModifierResource($modifier->load('options')),
        ], 201);
    }

    public function show(Modifier $modifier): JsonResponse
    {
        return response()->json([
            'data' => new ModifierResource($modifier->load('options')),
        ]);
    }

    public function update(UpdateModifierRequest $request, Modifier $modifier): JsonResponse
    {
        $modifier = $this->menuService->updateModifier($modifier->id, $request->validated());

        return response()->json([
            'data' => new ModifierResource($modifier->load('options')),
        ]);
    }

    public function destroy(Modifier $modifier): Response
    {
        $this->menuService->deleteModifier($modifier->id);

        return response()->noContent();
    }
}
