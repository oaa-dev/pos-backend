<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BranchProductStockResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'branch_id' => $this->branch_id,
            'product_id' => $this->product_id,
            'quantity_on_hand' => $this->quantity_on_hand,
            'average_cost' => $this->when($request->user()?->can('products.cost'), $this->average_cost),
            'reorder_point' => $this->reorder_point,
            'reorder_quantity' => $this->reorder_quantity,
            'is_low' => $this->reorder_point > 0 && $this->quantity_on_hand <= $this->reorder_point,
            'last_counted_at' => $this->last_counted_at?->format('Y-m-d h:i:s a'),
            'product' => $this->whenLoaded('product', fn () => new ProductResource($this->product)),
            'branch' => $this->whenLoaded('branch', fn () => new BranchResource($this->branch)),
        ];
    }
}
