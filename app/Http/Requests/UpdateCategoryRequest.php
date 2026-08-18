<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'store_id' => ['sometimes', 'required', 'integer', 'exists:stores,id'],
            'name' => ['sometimes', 'string', 'max:255'],
            'slug' => [
                'sometimes', 'string', 'max:255',
                Rule::unique('categories', 'slug')
                    ->where(fn ($query) => $query->where(
                        'store_id',
                        $this->input('store_id', $this->route('category')?->store_id),
                    ))
                    ->ignore($this->route('category')),
            ],
            // A category cannot be its own parent; deeper cycles are left to
            // the caller until the catalogue UI exists in Phase 1.
            'parent_id' => [
                'nullable', 'integer', 'exists:categories,id',
                Rule::notIn([$this->route('category')?->id]),
            ],
        ];
    }
}
