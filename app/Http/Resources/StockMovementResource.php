<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StockMovementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ulid' => $this->ulid,
            'branch_id' => $this->branch_id,
            'product_id' => $this->product_id,
            'quantity_entered' => $this->quantity_entered,
            'quantity_base' => $this->quantity_base,
            'type' => $this->type,
            'batch_id' => $this->batch_id,
            // Cost is the markup in disguise, so it follows the same
            // permission as products.cost rather than riding along free.
            // Both follow `products.cost` — cost is the markup in disguise, so
            // neither rides along free. `unit_cost` is per base unit;
            // `unit_cost_entered` is what was typed, against the unit picked.
            'unit_cost' => $this->when($request->user()?->can('products.cost'), $this->unit_cost),
            'unit_cost_entered' => $this->when($request->user()?->can('products.cost'), $this->unit_cost_entered),
            'note' => $this->note,
            'occurred_at' => $this->occurred_at?->format('Y-m-d h:i:s a'),
            'product' => $this->whenLoaded('product', fn () => new ProductResource($this->product)),
            'product_unit' => $this->whenLoaded('productUnit', fn () => new ProductUnitResource($this->productUnit)),
            'user' => $this->whenLoaded('user', fn () => new UserResource($this->user)),
        ];
    }
}
