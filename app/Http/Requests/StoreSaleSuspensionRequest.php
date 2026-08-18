<?php

namespace App\Http\Requests;

use App\Enums\SaleDiscountTypeEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSaleSuspensionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            // How the tindera finds it again — "Aling Nena", "yung naka-pula".
            'label' => ['nullable', 'string', 'max:64'],
            'payload' => ['required', 'array'],
            'payload.uuid' => ['nullable', 'uuid'],
            'payload.items' => ['required', 'array', 'min:1'],
            'payload.items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'payload.items.*.product_unit_id' => ['required', 'integer', 'exists:product_units,id'],
            'payload.items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'payload.items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'payload.items.*.product_name' => ['nullable', 'string', 'max:255'],
            'payload.items.*.unit_name' => ['nullable', 'string', 'max:255'],
            'payload.items.*.line_discount' => ['nullable', 'numeric', 'min:0'],
            'payload.items.*.discount_eligible' => ['nullable', 'boolean'],
            'payload.items.*.conversion_factor' => ['nullable', 'numeric', 'gt:0'],
            'payload.customer' => ['nullable', 'array'],
            'payload.customer.id' => ['required_with:payload.customer', 'integer', 'exists:customers,id'],
            'payload.discount' => ['nullable', 'array'],
            'payload.discount.type' => ['required_with:payload.discount', Rule::enum(SaleDiscountTypeEnum::class)],
            'payload.discount.percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'payload.discount.amount' => ['nullable', 'numeric', 'min:0'],
            'payload.discount.id_number' => ['nullable', 'string', 'max:64'],
            'payload.discount.customer_name' => ['nullable', 'string', 'max:255'],
            'payload.discount.reason' => ['nullable', 'string', 'max:255'],
        ];
    }
}
