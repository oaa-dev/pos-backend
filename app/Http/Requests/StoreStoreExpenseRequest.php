<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStoreExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'store_id' => ['sometimes', 'integer', 'exists:stores,id'],
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'expense_category_id' => ['required', 'integer', 'exists:expense_categories,id'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'description' => ['nullable', 'string', 'max:255'],
            // Only `drawer` affects the shift count.
            'paid_from' => ['nullable', Rule::in(['drawer', 'owner_pocket', 'bank'])],
            'supplier_id' => ['nullable', 'integer', 'exists:suppliers,id'],
            'incurred_at' => ['nullable', 'date'],
        ];
    }
}
