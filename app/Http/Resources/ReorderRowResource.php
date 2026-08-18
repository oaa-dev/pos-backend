<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property array<string, mixed> $resource
 */
class ReorderRowResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $row = $this->resource;

        return [
            'product_id' => $row['product_id'],
            'product_name' => $row['product_name'],
            'base_unit' => $row['base_unit'],
            'sold_base' => $row['sold_base'],
            'returned_base' => $row['returned_base'],
            'net_sold_base' => $row['net_sold_base'],
            'avg_daily' => $row['avg_daily'],
            'quantity_on_hand' => $row['quantity_on_hand'],
            'days_cover' => $row['days_cover'],
            'reorder_point' => $row['reorder_point'],
            'target_stock' => $row['target_stock'],
            'suggested_base' => $row['suggested_base'],
            'suggested_quantity' => $row['suggested_quantity'],
            'purchase_unit' => $row['purchase_unit'],
        ];
    }
}
