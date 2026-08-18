<?php

namespace App\Http\Requests;

use App\Enums\StockAdjustmentReasonEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStockAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'reason' => ['required', Rule::enum(StockAdjustmentReasonEnum::class)],
            'note' => ['nullable', 'string', 'max:255'],

            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.product_unit_id' => ['required', 'integer', 'exists:product_units,id'],
            // Signed and non-zero: negative removes stock, positive adds it.
            'items.*.quantity' => ['required', 'numeric', 'not_in:0'],
            'items.*.note' => ['nullable', 'string', 'max:255'],
        ];
    }
}
