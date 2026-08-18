<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUserProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'firstname' => ['sometimes', 'required', 'string', 'max:255'],
            'lastname' => ['sometimes', 'required', 'string', 'max:255'],
            'middlename' => ['sometimes', 'nullable', 'string', 'max:255'],
            'suffix' => ['sometimes', 'nullable', 'string', 'max:255'],
            'salutation' => ['sometimes', 'nullable', 'string', 'max:255'],
            'gender' => ['sometimes', 'nullable', 'string', 'max:255'],
            'birthdate' => ['sometimes', 'nullable', 'date'],

            'address' => ['sometimes', 'nullable', 'array'],

            'address.region_id' => ['required_with:address', 'integer', 'exists:regions,id'],
            'address.province_id' => ['nullable', 'integer', 'exists:provinces,id'],
            'address.city_id' => ['required_with:address', 'integer', 'exists:cities,id'],
            'address.barangay_id' => ['required_with:address', 'integer', 'exists:barangays,id'],
            'address.address_line' => ['nullable', 'string', 'max:255'],
            'address.postal_code' => ['nullable', 'string', 'max:20'],
            'address.type' => ['nullable', 'string', 'max:50'],
            'address.is_default' => ['nullable', 'boolean'],
        ];
    }
}
