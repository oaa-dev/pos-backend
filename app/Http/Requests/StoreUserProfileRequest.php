<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreUserProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'firstname' => ['required', 'string', 'max:255'],
            'lastname' => ['required', 'string', 'max:255'],
            'middlename' => ['nullable', 'string', 'max:255'],
            'suffix' => ['nullable', 'string', 'max:255'],
            'salutation' => ['nullable', 'string', 'max:255'],
            'gender' => ['nullable', 'string', 'max:255'],
            'birthdate' => ['nullable', 'date'],

            'address' => ['nullable', 'array'],

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
