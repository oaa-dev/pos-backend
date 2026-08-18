<?php

namespace App\Services;

use App\Data\ProductData;
use App\Data\ProductImageData;
use App\Data\ProductUnitData;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Repositories\Contracts\ProductRepositoryInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Spatie\LaravelData\Optional;
use Throwable;

class ProductService extends BaseService
{
    private const RELATIONS = ['category', 'baseUnit', 'units.unit'];

    public function __construct(
        protected readonly ProductRepositoryInterface $productRepository
    ) {
        parent::__construct($productRepository);
    }

    public function store(ProductData $data): Product
    {
        return DB::transaction(function () use ($data) {
            $product = $this->productRepository->create([
                ...$this->attributes($data),
                'sku' => $this->sku($data),
                'track_stock' => true,
                'is_service' => false,
            ]);

            if (! $data->units instanceof Optional) {
                $this->syncUnits($product, $data->units);
            }

            return $product->load(self::RELATIONS);
        });
    }

    public function updateProduct(Product $product, ProductData $data): Product
    {
        return DB::transaction(function () use ($product, $data) {
            $values = [
                ...$this->attributes($data),
                'track_stock' => true,
                'is_service' => false,
            ];

            if ($values !== []) {
                $product = $this->productRepository->update($product, $values);
            }

            if (! $data->units instanceof Optional) {
                $this->syncUnits($product, $data->units);
            }

            return $product->load(self::RELATIONS);
        });
    }

    /**
     * Resolve a scan to the product and the unit the code is printed on.
     *
     * There is no fallback any more. A barcode lives on a selling unit, so a
     * found code always names one — which is what removed the old
     * `matched_unit` flag and the default-unit guess behind it. A box barcode
     * sells a box because it cannot mean anything else.
     *
     * @return array{product: Product, product_unit_id: int}|null
     */
    public function resolveBarcode(string $barcode): ?array
    {
        $unit = $this->productRepository->findBarcode($barcode);

        if ($unit === null || $unit->product === null) {
            return null;
        }

        return [
            'product' => $unit->product,
            'product_unit_id' => $unit->id,
        ];
    }

    public function favorites(): mixed
    {
        return $this->productRepository->favorites(self::RELATIONS);
    }

    public function updateImage(Product $product, ProductImageData $data): Product
    {
        $path = $data->image->store("product-images/{$product->store_id}", 'public');

        if ($path === false) {
            throw new RuntimeException('Could not store the product image.');
        }

        $previous = $product->image_path;

        try {
            $this->productRepository->update($product, ['image_path' => $path]);
        } catch (Throwable $exception) {
            Storage::disk('public')->delete($path);

            throw $exception;
        }

        if ($previous !== null && $previous !== $path) {
            Storage::disk('public')->delete($previous);
        }

        return $product->refresh()->load(self::RELATIONS);
    }

    public function deleteImage(Product $product): Product
    {
        $previous = $product->image_path;
        $this->productRepository->update($product, ['image_path' => null]);

        if ($previous !== null) {
            Storage::disk('public')->delete($previous);
        }

        return $product->refresh()->load(self::RELATIONS);
    }

    /**
     * Rewrite a product's selling units, recording a price-history row for
     * every price that actually moved.
     *
     * @param  list<ProductUnitData>  $units
     */
    private function syncUnits(Product $product, array $units): void
    {
        $this->guardUnitInvariants($units);

        $keptIds = [];

        foreach ($units as $unitData) {
            $existing = $product->units()->where('unit_id', $unitData->unit_id)->first();

            $values = [
                'unit_id' => $unitData->unit_id,
                'conversion_factor' => $unitData->conversion_factor,
                'selling_price' => $unitData->selling_price,
                'is_base' => $unitData->is_base,
                'is_default_sale_unit' => $unitData->is_default_sale_unit,
                'sort_order' => $unitData->sort_order,
            ];

            // Omitted means "not submitted" and leaves the existing code
            // alone; an explicit null clears it.
            if (! $unitData->barcode instanceof Optional) {
                $values['barcode'] = filled($unitData->barcode) ? $unitData->barcode : null;
            }

            if ($existing === null) {
                $productUnit = $this->productRepository->createUnit($product, $values);
                $this->recordPriceChange($productUnit, null, $unitData->selling_price);

                $keptIds[] = $productUnit->id;

                continue;
            }

            $oldPrice = $existing->selling_price;
            $productUnit = $this->productRepository->updateUnit($existing, $values);

            // bccomp, not !==: these are decimal strings and "8.00" vs "8.0"
            // must not read as a price change.
            if (bccomp((string) $oldPrice, (string) $unitData->selling_price, 2) !== 0) {
                $this->recordPriceChange($productUnit, (float) $oldPrice, $unitData->selling_price);
            }

            $keptIds[] = $productUnit->id;
        }

        $this->productRepository->deleteUnitsExcept($product, $keptIds);
    }

    /**
     * Two invariants hold the multi-UOM model together. Without the first,
     * stock has no anchor and every conversion is meaningless; without the
     * second, the POS cannot decide which unit to preselect.
     *
     * @param  list<ProductUnitData>  $units
     */
    private function guardUnitInvariants(array $units): void
    {
        // An empty list is legitimate: a product is created before its selling
        // units exist, and clearing them back out is the same state. Every
        // invariant below only has meaning once there is a unit to check.
        if ($units === []) {
            return;
        }

        $bases = array_filter($units, fn (ProductUnitData $u) => $u->is_base);

        if (count($bases) !== 1) {
            throw new \InvalidArgumentException('A product must have exactly one base unit.');
        }

        $base = array_values($bases)[0];

        if (bccomp((string) $base->conversion_factor, '1', 4) !== 0) {
            throw new \InvalidArgumentException('The base unit must have a conversion factor of 1.');
        }

        if (count(array_filter($units, fn (ProductUnitData $u) => $u->is_default_sale_unit)) !== 1) {
            throw new \InvalidArgumentException('A product must have exactly one default sale unit.');
        }

        foreach ($units as $unit) {
            if ($unit->conversion_factor <= 0) {
                throw new \InvalidArgumentException('Conversion factors must be greater than zero.');
            }

            // A non-base unit worth exactly one base unit is the base unit
            // under another name. It reads as a pack everywhere downstream —
            // "buy 8 dozen" when the figure meant 8 pieces — so it is refused
            // at the point it would be created.
            if (! $unit->is_base && bccomp((string) $unit->conversion_factor, '1', 4) === 0) {
                throw new \InvalidArgumentException(
                    'A selling unit other than the base must hold more than one base unit. Set its conversion factor, or remove it.'
                );
            }
        }
    }

    private function recordPriceChange(ProductUnit $productUnit, ?float $oldPrice, float $newPrice): void
    {
        $this->productRepository->recordPriceChange($productUnit, [
            'old_price' => $oldPrice,
            'new_price' => $newPrice,
            'changed_by' => Auth::id(),
            'effective_at' => now(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    /**
     * Generated, not typed.
     *
     * A SKU is an internal handle nobody reads aloud, so it is minted the way
     * Purchasing mints PO and receipt numbers rather than asking for one. Only
     * on create — regenerating on every save would break every report and
     * label that already quotes it.
     */
    private function sku(ProductData $data): string
    {
        if (! $data->sku instanceof Optional && filled($data->sku)) {
            return $data->sku;
        }

        do {
            $sku = 'SKU-'.Str::upper(Str::random(8));
        } while ($this->productRepository->findBy('sku', $sku) !== null);

        return $sku;
    }

    private function attributes(ProductData $data): array
    {
        $map = [
            'store_id' => $data->store_id,
            'name' => $data->name,
            'sku' => $data->sku,
            'category_id' => $data->category_id,
            'base_unit_id' => $data->base_unit_id,
            'is_perishable' => $data->is_perishable,
            'is_favorite' => $data->is_favorite,
            'favorite_sort' => $data->favorite_sort,
            'status' => $data->status,
        ];

        // Optional means "not submitted" and is dropped; an explicit null is a
        // real value (clear the category, clear the SKU) and is kept.
        return array_filter($map, fn ($value) => ! $value instanceof Optional);
    }
}
