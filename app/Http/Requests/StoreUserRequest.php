<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Admin-side creation. No password is submitted — the account is created
     * with `config('users.default_password')`. `role_id` is optional, so a
     * user may be created without a role.
     */
    public function rules(): array
    {
        return [
            // `users` carries no `store_id`; this names the store whose
            // `store_users` row the account gets. Without it a new account
            // belongs to no store, its `GET /store` 404s, and it shows up in
            // nobody's staff list.
            'store_id' => ['required', 'integer', 'exists:stores,id'],
            'firstname' => ['required', 'string', 'max:255'],
            'lastname' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'phone_number' => ['nullable', 'string', 'max:255'],
            // Checked here rather than left to the foreign key: an unknown id
            // otherwise reaches the database and surfaces as a 500 instead of
            // a field error. Roles are global, so existence is the whole rule.
            'role_id' => ['nullable', 'integer', Rule::exists('roles', 'id')],
        ];
    }
}
