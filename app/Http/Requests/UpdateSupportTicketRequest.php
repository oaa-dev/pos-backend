<?php

namespace App\Http\Requests;

use App\Enums\SupportPriorityEnum;
use App\Enums\SupportTicketStatusEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The operator's side of the ticket.
 *
 * **`subject` and `body` are deliberately absent.** They are the customer's own
 * words, and support editing the report it is answering is not a thing this
 * should permit — the reply thread is where the operator writes.
 */
class UpdateSupportTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['sometimes', 'required', Rule::enum(SupportTicketStatusEnum::class)],
            'priority' => ['sometimes', 'required', Rule::enum(SupportPriorityEnum::class)],
        ];
    }
}
