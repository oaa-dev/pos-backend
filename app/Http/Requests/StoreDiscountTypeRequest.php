<?php

namespace App\Http\Requests;

use App\Enums\StatusEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDiscountTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('discount_types', 'slug')],
            // Null means the discount is entered as a peso amount at the till
            // rather than a fixed rate.
            'percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'requires_identification' => ['nullable', 'boolean'],
            'status' => ['nullable', 'string', Rule::in(StatusEnum::values())],
        ];
    }
}
