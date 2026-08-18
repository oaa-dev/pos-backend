<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SaleItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'product_unit_id' => $this->product_unit_id,
            // The snapshots, not the live product — a reprinted receipt must
            // read the same next year.
            'product_name' => $this->product_name_snapshot,
            'unit_name' => $this->unit_name_snapshot,
            'quantity' => $this->quantity,
            'quantity_base' => $this->quantity_base,
            'unit_price' => $this->unit_price,
            'line_discount' => $this->line_discount,
            'line_total' => $this->line_total,
            // Cost is the markup in disguise.
            'unit_cost' => $this->when($request->user()?->can('products.cost'), $this->unit_cost),
            'gross_profit' => $this->when(
                $request->user()?->can('products.cost'),
                fn () => $this->grossProfit(),
            ),
        ];
    }
}
