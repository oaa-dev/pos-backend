<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists as ExistsRule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'firstname' => ['sometimes', 'string', 'max:255'],
            'lastname' => ['sometimes', 'string', 'max:255'],
            'email' => [
                'sometimes',
                'email',
                Rule::unique('users', 'email')->ignore($this->route('user')),
            ],
            'phone_number' => ['sometimes', 'nullable', 'string', 'max:255'],
            'role_id' => ['sometimes', 'nullable', 'integer', $this->roleInOwnStore()],
            'birthdate' => ['sometimes', 'nullable', 'date', 'before_or_equal:today'],
        ];
    }

    /**
     * Any of the three roles.
     *
     * Roles are global now — `superadmin`, `owner`, `tindera`, seeded once and
     * shared — so there is no per-store copy to narrow to and a plain
     * existence check is the whole rule.
     */
    protected function roleInOwnStore(): ExistsRule
    {
        return Rule::exists('roles', 'id');
    }
}
