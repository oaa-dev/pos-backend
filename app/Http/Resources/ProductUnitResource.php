<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductUnitResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'unit_id' => $this->unit_id,
            // The code printed on this unit — a box barcode sells a box.
            'barcode' => $this->barcode,
            'unit' => $this->whenLoaded('unit', fn () => new UnitResource($this->unit)),
            'conversion_factor' => $this->conversion_factor,
            'selling_price' => $this->selling_price,
            'is_base' => $this->is_base,
            'is_default_sale_unit' => $this->is_default_sale_unit,
            'sort_order' => $this->sort_order,
        ];
    }
}
