<?php

namespace App\Http\Requests;

use App\Enums\StatusEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBranchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $storeId = $this->input('store_id', $this->route('branch')?->store_id);

        return [
            'store_id' => ['sometimes', 'required', 'integer', 'exists:stores,id'],
            'name' => ['sometimes', 'string', 'max:255'],
            'code' => [
                'sometimes', 'string', 'max:32',
                Rule::unique('branches', 'code')
                    ->where(fn ($query) => $query->where('store_id', $storeId))
                    ->ignore($this->route('branch')),
            ],
            'phone' => ['nullable', 'string', 'max:32'],
            'status' => ['sometimes', 'string', Rule::in(StatusEnum::values())],
            'opened_at' => ['nullable', 'date'],

            'address' => ['sometimes', 'array'],
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
