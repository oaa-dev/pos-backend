<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Soft-deleted units keep their row, so uniqueness has to consider
            // them: reusing the name of a retired unit would make two rows
            // answer to the same name once one is restored.
            'name' => ['required', 'string', 'max:255', Rule::unique('units', 'name')],
            'abbreviation' => ['nullable', 'string', 'max:16'],
            'allows_fraction' => ['nullable', 'boolean'],
        ];
    }
}
