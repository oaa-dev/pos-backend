<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StockTransferItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'product_unit_id' => $this->product_unit_id,
            'quantity' => $this->quantity,
            'product' => $this->whenLoaded('product', fn () => new ProductResource($this->product)),
            'product_unit' => $this->whenLoaded('productUnit', fn () => new ProductUnitResource($this->productUnit)),
        ];
    }
}
