<?php

namespace App\Repositories\Contracts;

use App\Models\Product;
use App\Models\ProductUnit;
use Illuminate\Database\Eloquent\Collection;

interface ProductRepositoryInterface extends BaseRepositoryInterface
{
    public function findBarcode(string $barcode): ?ProductUnit;

    /**
     * @param  list<string>  $relations
     */
    public function favorites(array $relations = []): Collection;

    /**
     * @param  array<string, mixed>  $values
     */
    public function createUnit(Product $product, array $values): ProductUnit;

    /**
     * @param  array<string, mixed>  $values
     */
    public function updateUnit(ProductUnit $productUnit, array $values): ProductUnit;

    /**
     * @param  list<int>  $keepIds
     */
    public function deleteUnitsExcept(Product $product, array $keepIds): void;

    /**
     * @param  array<string, mixed>  $values
     */
    public function recordPriceChange(ProductUnit $productUnit, array $values): void;

    /**
     * @param  list<string>  $barcodes
     */
}
