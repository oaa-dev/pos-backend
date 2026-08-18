<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DashboardOverviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // The window the movement figures cover. Today's takings ignore it.
            'days' => ['nullable', 'integer', 'min:1', 'max:365'],
        ];
    }
}
