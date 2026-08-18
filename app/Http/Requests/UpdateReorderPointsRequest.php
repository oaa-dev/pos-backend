<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateReorderPointsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // A list, so accepting every suggestion and correcting a single
            // product are the same call.
            'points' => ['required', 'array', 'min:1'],
            'points.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'points.*.reorder_point' => ['required', 'numeric', 'min:0'],
            'points.*.reorder_quantity' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
