<?php

namespace App\Http\Requests;

use App\Enums\StatusEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Opening a store, optionally with the user account that will run it.
 *
 * The account becomes a normal `users` row in the new tenant with that store's
 * Owner role. `slug` is derived, never submitted: it is the one globally
 * unique handle a store has.
 */
class StoreManagedStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'status' => ['sometimes', Rule::in(StatusEnum::values())],

            'account' => ['sometimes', 'array'],
            'account.firstname' => ['required_with:account', 'string', 'max:255'],
            'account.lastname' => ['required_with:account', 'string', 'max:255'],
            'account.email' => ['required_with:account', 'email', 'unique:users,email'],
            'account.password' => ['required_with:account', 'confirmed', Password::defaults()],
            'account.phone_number' => ['nullable', 'string', 'max:255'],
        ];
    }
}
