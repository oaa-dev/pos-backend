<?php

namespace App\Http\Requests;

use App\Enums\StatusEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Trusted as sent. Nothing checks that the caller belongs to this
            // store — see the accepted risk in the tenancy plan.
            'store_id' => ['required', 'integer', 'exists:stores,id'],
            'name' => ['required', 'string', 'max:255'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'base_unit_id' => ['required', 'integer', 'exists:units,id'],
            'is_perishable' => ['nullable', 'boolean'],
            'is_favorite' => ['nullable', 'boolean'],
            'favorite_sort' => ['nullable', 'integer', 'min:0'],
            'status' => ['nullable', Rule::in(StatusEnum::values())],

            // Optional: a product is created on its own, and its selling
            // units are set up afterwards in their own screen.
            'units' => ['nullable', 'array'],
            'units.*.unit_id' => ['required', 'integer', 'exists:units,id'],
            'units.*.conversion_factor' => ['required', 'numeric', 'gt:0'],
            'units.*.selling_price' => ['required', 'numeric', 'min:0'],
            'units.*.is_base' => ['required', 'boolean'],
            'units.*.is_default_sale_unit' => ['required', 'boolean'],
            'units.*.sort_order' => ['nullable', 'integer', 'min:0'],
            // The code printed on that unit. Unique across every unit, so a
            // scan resolves to exactly one thing to sell.
            'units.*.barcode' => [
                'nullable', 'string', 'max:64', 'distinct',
                Rule::unique('product_units', 'barcode'),
            ],
        ];
    }
}
