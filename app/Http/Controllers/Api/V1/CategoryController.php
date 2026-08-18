<?php

namespace App\Http\Controllers\Api\V1;

use App\Data\CategoryData;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use App\Services\CategoryService;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    use ApiResponse;

    public function __construct(protected CategoryService $categoryService) {}

    public function index(Request $request)
    {
        return $this->paginatedResponse(
            CategoryResource::collection($this->categoryService->paginate($request->input('per_page', 50))),
        );
    }

    public function show(Category $category)
    {
        return $this->successResponse(
            new CategoryResource($category->load(['parent', 'children'])),
        );
    }

    public function store(StoreCategoryRequest $request)
    {
        $category = $this->categoryService->store(CategoryData::from($request->validated()));

        return $this->successResponse(new CategoryResource($category), 'Category created successfully', 201);
    }

    public function update(UpdateCategoryRequest $request, Category $category)
    {
        $category = $this->categoryService->updateCategory($category, CategoryData::from($request->validated()));

        return $this->successResponse(new CategoryResource($category), 'Category updated successfully');
    }

    /**
     * Retires rather than erases — see CategoryService::retire().
     */
    public function destroy(Category $category)
    {
        $this->categoryService->retire($category);

        return $this->successResponse(null, 'Category retired successfully');
    }
}
