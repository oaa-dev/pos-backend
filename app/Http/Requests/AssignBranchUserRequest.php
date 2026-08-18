<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignBranchUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Narrowed to the bound branch's own store, not merely to a user
            // that exists. Scoping the *dropdown* is cosmetic — this is the
            // write path, and without it a crafted request attaches another
            // customer's staff to this branch. Listing guarded and detail path
            // not is the branch-scoping defect this codebase already paid for
            // once.
            'user_id' => [
                'required',
                'integer',
                Rule::exists('store_users', 'user_id')
                    ->where('store_id', $this->route('branch')?->store_id),
            ],
            'is_primary' => ['nullable', 'boolean'],
        ];
    }
}
