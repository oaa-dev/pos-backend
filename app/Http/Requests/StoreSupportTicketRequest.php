<?php

namespace App\Http\Requests;

use App\Enums\SupportCategoryEnum;
use App\Enums\SupportPriorityEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * `store_id` is scoped to the actor's own membership, not merely to a store
 * that exists.
 *
 * A bare `exists:stores,id` — which twelve other requests in this codebase
 * still use — would let an owner file a ticket against another customer's
 * store. The platform operator belongs to no store and is refused here on
 * purpose: the operator answers tickets, they do not file them.
 */
class StoreSupportTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'store_id' => [
                'required',
                'integer',
                Rule::exists('store_users', 'store_id')->where('user_id', $this->user()?->id),
            ],
            'subject' => ['required', 'string', 'max:160'],
            'body' => ['required', 'string', 'max:5000'],
            'category' => ['required', Rule::enum(SupportCategoryEnum::class)],
            'priority' => ['required', Rule::enum(SupportPriorityEnum::class)],
        ];
    }

    public function messages(): array
    {
        return [
            'store_id.exists' => 'You can only raise a ticket for your own store.',
        ];
    }
}
