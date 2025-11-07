<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreModifierOptionRequest;
use App\Http\Requests\UpdateModifierOptionRequest;
use App\Http\Resources\ModifierOptionResource;
use App\Models\Modifier;
use App\Models\ModifierOption;
use App\Services\MenuWriteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class ModifierOptionController extends Controller
{
    public function __construct(
        private MenuWriteService $menuService
    ) {}

    public function store(StoreModifierOptionRequest $request, Modifier $modifier): JsonResponse
    {
        $option = $this->menuService->createModifierOption($modifier->id, $request->validated());

        return response()->json([
            'data' => new ModifierOptionResource($option),
        ], 201);
    }

    public function update(UpdateModifierOptionRequest $request, Modifier $modifier, ModifierOption $option): JsonResponse
    {
        $option = $this->menuService->updateModifierOption($option->id, $request->validated());

        return response()->json([
            'data' => new ModifierOptionResource($option),
        ]);
    }

    public function destroy(Modifier $modifier, ModifierOption $option): Response
    {
        $this->menuService->deleteModifierOption($option->id);

        return response()->noContent();
    }
}
