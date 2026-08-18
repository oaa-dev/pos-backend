<?php

namespace App\Http\Requests;

use App\Enums\StatusEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDiscountTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'slug' => [
                'sometimes', 'required', 'string', 'max:255',
                Rule::unique('discount_types', 'slug')->ignore($this->route('discountType')),
            ],
            'percentage' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:100'],
            'requires_identification' => ['sometimes', 'boolean'],
            'status' => ['sometimes', 'string', Rule::in(StatusEnum::values())],
        ];
    }
}
