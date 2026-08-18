<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => [
                'sometimes', 'required', 'string', 'max:255',
                Rule::unique('units', 'name')->ignore($this->route('unit')),
            ],
            'abbreviation' => ['sometimes', 'required', 'string', 'max:16'],
            'allows_fraction' => ['sometimes', 'boolean'],
        ];
    }
}
