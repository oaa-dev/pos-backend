<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/**
 * The account that will run an existing store.
 *
 * `role_id` is deliberately not accepted, for the same reason registration
 * refuses it: the person being added becomes that store's owner, and they get
 * *that* role in *that* store, never one the caller names.
 */
class StoreOwnerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'firstname' => ['required', 'string', 'max:255'],
            'lastname' => ['required', 'string', 'max:255'],

            // Globally unique, not per store — an email identifies a person,
            // and two stores sharing one would make login ambiguous. This is
            // the likeliest real failure on this form.
            'email' => ['required', 'email', 'unique:users,email'],

            'password' => ['required', 'confirmed', Password::defaults()],
            'phone_number' => ['nullable', 'string', 'max:255'],
        ];
    }
}
