<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

/**
 * Creating a transfer **moves the stock**, so this request is the guard.
 *
 * It used to be enough to validate `exists:branches,id` here, because the stock
 * only moved on `POST /stock-transfer/{id}/send` and `StockTransferPolicy`
 * checked the store on the way through. That two-step flow is gone: the create
 * request now issues and receives in one transaction, the policy with it. Both
 * branch ids are therefore scoped to the actor's own store below — without that,
 * any holder of `inventory.transfer` could move another customer's stock by
 * posting their branch ids.
 */
class StoreStockTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'from_branch_id' => ['required', 'different:to_branch_id', $this->branchInOwnStore()],
            'to_branch_id' => ['required', $this->branchInOwnStore()],
            'notes' => ['nullable', 'string', 'max:500'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.product_unit_id' => ['required', 'exists:product_units,id'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'from_branch_id.exists' => 'That branch does not belong to your store.',
            'to_branch_id.exists' => 'That branch does not belong to your store.',
        ];
    }

    /**
     * The branch must be one of the actor's own store's.
     *
     * Assignment is deliberately *not* checked: within one store, any holder of
     * `inventory.transfer` may move stock between any two branches. A tindera at
     * one branch pulling stock from another is ordinary in a business this size.
     *
     * The platform operator belongs to no store, so `$storeId` is null and every
     * branch is refused. That is the same refusal `StockTransferPolicy` made on
     * purpose before it was deleted: moving a customer's stock between their own
     * branches is the customer's operation, not support work. An operator who
     * genuinely needs to can be added to the store.
     */
    private function branchInOwnStore(): Exists
    {
        $storeId = $this->user()?->store?->id;

        // Spelt out rather than leaning on `where('store_id', null)`, which
        // Laravel quietly rewrites to `whereNull` on a column that is never
        // null — the same refusal, but by accident rather than on purpose.
        return $storeId === null
            ? Rule::exists('branches', 'id')->where(fn ($query) => $query->whereRaw('1 = 0'))
            : Rule::exists('branches', 'id')->where('store_id', $storeId);
    }
}
