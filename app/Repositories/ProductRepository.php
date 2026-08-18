<?php

namespace App\Repositories;

use App\Filters\GlobalSearchFilter;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Repositories\Contracts\ProductRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class ProductRepository extends BaseRepository implements ProductRepositoryInterface
{
    protected function model(): string
    {
        return Product::class;
    }

    protected function allowedFilters(): array
    {
        return [
            'store_id',
            // Barcodes live on the selling units now, and GlobalSearchFilter
            // expands relation.column to orWhereHas, so a partial code finds
            // the product it is printed on.
            AllowedFilter::custom('q', new GlobalSearchFilter([
                'name',
                'sku',
                'units.barcode',
                'category.name',
            ])),
            'name',
            'sku',
            'status',
            'category_id',
            'is_favorite',
            'is_perishable',
        ];
    }

    protected function allowedSorts(): array
    {
        return ['id', 'name', 'sku', 'created_at'];
    }

    protected function allowedIncludes(): array
    {
        return ['category', 'baseUnit', 'units', 'units.unit'];
    }

    protected function defaultSort(): string
    {
        return 'name';
    }

    /**
     * ProductResource puts every relation behind whenLoaded(), and
     * BaseRepository eager-loads nothing, so without this the index comes back
     * with no units — the field that makes a product sellable — and no error.
     */
    protected function query(): QueryBuilder
    {
        return parent::query()->with(['category', 'baseUnit', 'units.unit']);
    }

    /**
     * The selling unit a scanned code is printed on.
     *
     * Returning the *unit* rather than the product is the whole point: a box
     * barcode has to sell a box, and resolving to the product alone would sell
     * one sachet at the sachet price with nothing indicating anything was
     * wrong.
     */
    public function findBarcode(string $barcode): ?ProductUnit
    {
        return ProductUnit::where('barcode', $barcode)
            ->with(['product.units.unit', 'product.category', 'product.baseUnit'])
            ->first();
    }

    /**
     * @param  list<string>  $relations
     */
    public function favorites(array $relations = []): Collection
    {
        return $this->builder()
            ->active()
            ->favorites()
            ->with($relations)
            ->get();
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function createUnit(Product $product, array $values): ProductUnit
    {
        return $product->units()->create($values);
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function updateUnit(ProductUnit $productUnit, array $values): ProductUnit
    {
        $productUnit->update($values);

        return $productUnit->refresh();
    }

    /**
     * @param  list<int>  $keepIds
     */
    public function deleteUnitsExcept(Product $product, array $keepIds): void
    {
        $product->units()->whereNotIn('id', $keepIds)->delete();
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function recordPriceChange(ProductUnit $productUnit, array $values): void
    {
        $productUnit->priceHistory()->create($values);
    }

    /**
     * Accepts either bare codes or `{barcode, product_unit_id}` rows.
     *
     * The string form is kept so existing callers and any stored payload keep
     * working; it simply means "no unit assigned", which resolves to the
     * default sale unit at scan time.
     *
     * @param  list<string|array{barcode:string, product_unit_id?:int|null}>  $barcodes
     */
}
