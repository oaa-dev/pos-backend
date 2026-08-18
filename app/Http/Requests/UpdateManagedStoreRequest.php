<?php

namespace App\Http\Requests;

use App\Enums\StatusEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Wider than the owner's own `UpdateStoreRequest` by exactly one field:
 * `status`. Suspending a customer is the operator's decision, and a store must
 * not be able to un-suspend itself.
 *
 * `slug` is absent here too, for the operator as much as for the owner —
 * changing it is a migration-shaped problem, not a form field.
 */
class UpdateManagedStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'owner_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:32'],
            'status' => ['sometimes', Rule::in(StatusEnum::values())],
        ];
    }
}
