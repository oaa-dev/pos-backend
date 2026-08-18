<?php

namespace App\Services;

use App\Data\CategoryData;
use App\Models\Category;
use App\Repositories\Contracts\CategoryRepositoryInterface;
use Illuminate\Support\Str;
use Spatie\LaravelData\Optional;

class CategoryService extends BaseService
{
    public function __construct(
        protected readonly CategoryRepositoryInterface $categoryRepository
    ) {
        parent::__construct($categoryRepository);
    }

    public function store(CategoryData $data): Category
    {
        $name = $data->name instanceof Optional ? '' : $data->name;

        $category = $this->categoryRepository->create([
            'store_id' => $data->store_id instanceof Optional ? null : $data->store_id,
            'name' => $name,
            'slug' => $data->slug instanceof Optional ? Str::slug($name) : $data->slug,
            'parent_id' => $data->parent_id instanceof Optional ? null : $data->parent_id,
        ]);

        return $category->load('parent');
    }

    public function updateCategory(Category $category, CategoryData $data): Category
    {
        $values = array_filter([
            'name' => $data->name instanceof Optional ? null : $data->name,
            'slug' => $data->slug instanceof Optional ? null : $data->slug,
        ], fn ($value) => $value !== null);

        // parent_id is handled apart from array_filter: null is a meaningful
        // value here (promote to top level), not an omitted field.
        if (! $data->parent_id instanceof Optional) {
            $values['parent_id'] = $data->parent_id;
        }

        if ($values !== []) {
            $category = $this->categoryRepository->update($category, $values);
        }

        return $category->load('parent');
    }

    /**
     * Retire a category.
     *
     * Soft delete: `products.category_id` is nullOnDelete, so a hard delete
     * would silently unassign every product in the category — the same trap
     * `users.role_id` carries. Keeping the row means the reference survives,
     * and `Product::category()` reads it back with withTrashed().
     *
     * Children block the retire rather than being re-parented. Silently moving
     * a subtree up a level relocates products between categories with nothing
     * recording that it happened.
     */
    public function retire(Category $category): void
    {
        $children = $category->children()->count();

        if ($children > 0) {
            throw new \InvalidArgumentException(
                "Move or retire the {$children} sub-categories first."
            );
        }

        $this->categoryRepository->delete($category);
    }
}
