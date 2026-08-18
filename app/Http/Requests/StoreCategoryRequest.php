<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCategoryRequest extends FormRequest
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
            // Scoped, matching the composite unique on `(store_id, slug)`.
            'slug' => [
                'nullable', 'string', 'max:255',
                Rule::unique('categories', 'slug')
                    ->where(fn ($query) => $query->where('store_id', $this->input('store_id'))),
            ],
            'parent_id' => ['nullable', 'integer', 'exists:categories,id'],
        ];
    }
}
