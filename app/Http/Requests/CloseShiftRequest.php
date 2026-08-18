<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CloseShiftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'closing_counted' => ['required', 'numeric', 'min:0'],
            'closing_notes' => ['nullable', 'string', 'max:255'],
        ];
    }
}
