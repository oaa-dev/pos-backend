<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class WriteOffCreditRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'gt:0'],
            'note' => ['required', 'string', 'max:255'],
            // Forgiving debt is the owner's call, entered at the terminal.
            'approval_pin' => ['required', 'string', 'min:4', 'max:8'],
        ];
    }
}
