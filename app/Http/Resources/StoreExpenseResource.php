<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StoreExpenseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'store_id' => $this->store_id,
            'branch_id' => $this->branch_id,
            'expense_category_id' => $this->expense_category_id,
            'amount' => $this->amount,
            'description' => $this->description,
            'paid_from' => $this->paid_from,
            'affects_drawer' => $this->affectsDrawer(),
            'cash_drawer_session_id' => $this->cash_drawer_session_id,
            'incurred_at' => $this->incurred_at?->toDateString(),
            'is_approved' => $this->approved_by !== null,
            'category' => $this->whenLoaded('category', fn () => new ExpenseCategoryResource($this->category)),
            'branch' => $this->whenLoaded('branch', fn () => new BranchResource($this->branch)),
        ];
    }
}
