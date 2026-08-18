<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SaleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'sale_number' => $this->sale_number,
            'branch_id' => $this->branch_id,
            'cash_drawer_session_id' => $this->cash_drawer_session_id,
            'customer_id' => $this->customer_id,
            'status' => $this->status,
            'subtotal' => $this->subtotal,
            'discount_total' => $this->discount_total,
            'total' => $this->total,
            'amount_tendered' => $this->amount_tendered,
            'change_due' => $this->change_due,
            'credit_amount' => $this->credit_amount,
            'sold_at' => $this->sold_at?->format('Y-m-d h:i:s a'),
            'voided_at' => $this->voided_at?->format('Y-m-d h:i:s a'),
            'void_reason' => $this->void_reason,
            // Callback form throughout — a walk-in sale has no customer, which
            // is the loaded-but-null case that drops the key otherwise.
            'customer' => $this->whenLoaded('customer', fn () => new CustomerResource($this->customer)),
            'user' => $this->whenLoaded('user', fn () => new UserResource($this->user)),
            'branch' => $this->whenLoaded('branch', fn () => new BranchResource($this->branch)),
            'items' => $this->whenLoaded('items', fn () => SaleItemResource::collection($this->items)),
            'payments' => $this->whenLoaded('payments', fn () => SalePaymentResource::collection($this->payments)),
            'discounts' => $this->whenLoaded('discounts', fn () => SaleDiscountResource::collection($this->discounts)),
        ];
    }
}
