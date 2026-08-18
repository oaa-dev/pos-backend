<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CollectCreditRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'gt:0'],
            // Optional: a payment taken outside an open shift still records,
            // it just does not count toward that shift's expected cash.
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }
}
