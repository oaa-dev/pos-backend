<?php

namespace App\Http\Requests;

use App\Enums\CashMovementTypeEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCashMovementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(CashMovementTypeEnum::class)],
            // Always positive; the type carries the direction.
            'amount' => ['required', 'numeric', 'gt:0'],
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }
}
