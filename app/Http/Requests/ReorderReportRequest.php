<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReorderReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
            // How many days of stock to hold. Capped at a year because a
            // typo of 3650 would suggest ordering a decade of inventory.
            'cover_target_days' => ['nullable', 'integer', 'min:1', 'max:365'],
        ];
    }
}
