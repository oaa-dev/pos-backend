<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class VoidSaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:255'],
            // Entered at the terminal so the owner need not log the tindera out.
            'approval_pin' => ['required', 'string', 'min:4', 'max:8'],
        ];
    }
}
