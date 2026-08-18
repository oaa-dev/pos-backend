<?php

namespace App\Http\Requests;

use App\Enums\StatusEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBranchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'store_id' => ['required', 'integer', 'exists:stores,id'],
            'name' => ['required', 'string', 'max:255'],
            // Scoped, matching the composite unique on `(store_id, code)` —
            // two shops may both call their first branch MAIN.
            'code' => [
                'nullable', 'string', 'max:32',
                Rule::unique('branches', 'code')
                    ->where(fn ($query) => $query->where('store_id', $this->input('store_id'))),
            ],
            'phone' => ['nullable', 'string', 'max:32'],
            'status' => ['nullable', 'string', Rule::in(StatusEnum::values())],
            'opened_at' => ['nullable', 'date'],

            'address' => ['nullable', 'array'],
            'address.region_id' => ['required_with:address', 'integer', 'exists:regions,id'],
            'address.province_id' => ['nullable', 'integer', 'exists:provinces,id'],
            'address.city_id' => ['required_with:address', 'integer', 'exists:cities,id'],
            'address.barangay_id' => ['required_with:address', 'integer', 'exists:barangays,id'],
            'address.address_line' => ['nullable', 'string', 'max:255'],
            'address.postal_code' => ['nullable', 'string', 'max:16'],
            'address.type' => ['nullable', 'string', 'max:32'],
            'address.is_default' => ['nullable', 'boolean'],
        ];
    }
}
