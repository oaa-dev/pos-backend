<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStockReceiptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'product_unit_id' => ['required', 'integer', 'exists:product_units,id'],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'unit_cost' => ['nullable', 'numeric', 'min:0'],

            // Optional, and together they decide what this is. Naming a
            // supplier or a payment makes it a purchase; naming neither makes
            // it an opening balance counted in with no money behind it.
            'supplier_id' => ['nullable', 'integer', 'exists:suppliers,id'],
            'paid_from' => ['nullable', Rule::in(['drawer', 'owner_pocket', 'bank'])],
            'expiry_date' => ['nullable', 'date'],
            'batch_code' => ['nullable', 'string', 'max:64'],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }
}
