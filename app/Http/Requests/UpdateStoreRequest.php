<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * `slug` and `status` are not merely unvalidated — they are absent from the
 * rules, so `validated()` never carries them and `StoreData` has nowhere to
 * put them. See StoreData for why each is withheld.
 */
class UpdateStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'owner_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:32'],
        ];
    }
}
