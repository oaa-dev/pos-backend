<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductBatchResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'branch_id' => $this->branch_id,
            'product_id' => $this->product_id,
            'batch_code' => $this->batch_code,
            'expiry_date' => $this->expiry_date?->toDateString(),
            'days_to_expiry' => $this->expiry_date
                ? (int) now()->startOfDay()->diffInDays($this->expiry_date, false)
                : null,
            'quantity_remaining' => $this->quantity_remaining,
            'unit_cost' => $this->when($request->user()?->can('products.cost'), $this->unit_cost),
            'status' => $this->status,
            'received_at' => $this->received_at?->format('Y-m-d h:i:s a'),
            'product' => $this->whenLoaded('product', fn () => new ProductResource($this->product)),
        ];
    }
}
