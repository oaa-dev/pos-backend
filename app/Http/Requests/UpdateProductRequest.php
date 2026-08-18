<?php

namespace App\Http\Requests;

use App\Enums\StatusEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $productId = $this->route('product')?->id;

        $storeId = $this->input('store_id', $this->route('product')?->store_id);

        return [
            'store_id' => ['sometimes', 'required', 'integer', 'exists:stores,id'],
            'name' => ['sometimes', 'string', 'max:255'],
            // Scoped to the store, matching the composite unique on
            // `(store_id, sku)`. A global rule here would stop the second
            // customer reusing a SKU the database is happy to accept.
            'sku' => [
                'nullable', 'string', 'max:64',
                Rule::unique('products', 'sku')
                    ->where(fn ($query) => $query->where('store_id', $storeId))
                    ->ignore($productId),
            ],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'base_unit_id' => ['sometimes', 'integer', 'exists:units,id'],
            'is_perishable' => ['sometimes', 'boolean'],
            'is_favorite' => ['sometimes', 'boolean'],
            'favorite_sort' => ['sometimes', 'integer', 'min:0'],
            'status' => ['sometimes', Rule::in(StatusEnum::values())],

            'units' => ['sometimes', 'array', 'min:1'],
            'units.*.unit_id' => ['required', 'integer', 'exists:units,id'],
            'units.*.conversion_factor' => ['required', 'numeric', 'gt:0'],
            'units.*.selling_price' => ['required', 'numeric', 'min:0'],
            'units.*.is_base' => ['required', 'boolean'],
            'units.*.is_default_sale_unit' => ['required', 'boolean'],
            'units.*.sort_order' => ['nullable', 'integer', 'min:0'],
            // Unique against *other* products' units. Resubmitting a unit's
            // own existing code is the normal case on every save, so the
            // product's own rows are excluded rather than tripping the rule;
            // `distinct` still catches the same code twice in one payload.
            'units.*.barcode' => [
                'nullable', 'string', 'max:64', 'distinct',
                Rule::unique('product_units', 'barcode')
                    ->where(fn ($query) => $query->where('product_id', '!=', $productId)),
            ],
        ];
    }
}
