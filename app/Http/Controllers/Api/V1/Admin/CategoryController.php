<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use App\Services\MenuWriteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CategoryController extends Controller
{
    public function __construct(
        private MenuWriteService $menuService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $categories = Category::orderBy('position')->get();

        return response()->json([
            'data' => CategoryResource::collection($categories),
            'meta' => [
                'total' => $categories->count(),
            ],
        ]);
    }

    public function store(StoreCategoryRequest $request): JsonResponse
    {
        $category = $this->menuService->createCategory($request->validated());

        return response()->json([
            'data' => new CategoryResource($category),
        ], 201);
    }

    public function show(Category $category): JsonResponse
    {
        return response()->json([
            'data' => new CategoryResource($category),
        ]);
    }

    public function update(UpdateCategoryRequest $request, Category $category): JsonResponse
    {
        $category = $this->menuService->updateCategory($category->id, $request->validated());

        return response()->json([
            'data' => new CategoryResource($category),
        ]);
    }

    public function destroy(Request $request, Category $category): Response
    {
        $force = $request->boolean('force', false);
        $this->menuService->deleteCategory($category->id, $force);

        return response()->noContent();
    }
}
