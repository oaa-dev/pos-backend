<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Public self-registration, which under tenancy means opening a store.
     *
     * `role_id` is still deliberately not accepted. The registrant becomes the
     * owner of the store they just created — anything less would hand them a
     * shop they cannot use, since the API is default-deny — but they get *that*
     * role, in *that* store, never one they name themselves.
     *
     * `store_name` is optional because signup proper is a later phase; without
     * it the shop is named after the registrant.
     */
    public function rules(): array
    {
        return [
            'store_name' => ['nullable', 'string', 'max:255'],
            'firstname' => ['required', 'string', 'max:255'],
            'lastname' => ['required', 'string', 'max:255'],

            // Globally unique, not per store: an email identifies a person,
            // and two stores sharing one would make login ambiguous.
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'phone_number' => ['nullable', 'string', 'max:255'],
        ];
    }
}
