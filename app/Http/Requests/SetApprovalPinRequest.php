<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SetApprovalPinRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'approval_pin' => ['required', 'string', 'min:4', 'max:8', 'regex:/^[0-9]+$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'approval_pin.regex' => 'The approval PIN must be digits only.',
        ];
    }
}
