<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSaleReturnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['reason' => ['required', 'string', 'max:500'], 'refund_method' => ['required', 'in:cash,credit,exchange'], 'items' => ['required', 'array', 'min:1'], 'items.*.sale_item_id' => ['required', 'exists:sale_items,id'], 'items.*.quantity' => ['required', 'numeric', 'gt:0'], 'items.*.restock' => ['required', 'boolean']];
    }
}
