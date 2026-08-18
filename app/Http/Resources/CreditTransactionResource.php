<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CreditTransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'customer_id' => $this->customer_id,
            'branch_id' => $this->branch_id,
            'type' => $this->type,
            'amount' => $this->amount,
            'balance_after' => $this->balance_after,
            'outstanding' => $this->outstanding,
            'sale_id' => $this->sale_id,
            'due_date' => $this->due_date?->toDateString(),
            'is_overdue' => $this->isOverdue(),
            'age_in_days' => $this->ageInDays(),
            'note' => $this->note,
            'occurred_at' => $this->occurred_at?->format('Y-m-d h:i:s a'),
            'customer' => $this->whenLoaded('customer', fn () => new CustomerResource($this->customer)),
            'sale' => $this->whenLoaded('sale', fn () => new SaleResource($this->sale)),
            'user' => $this->whenLoaded('user', fn () => new UserResource($this->user)),
        ];
    }
}
