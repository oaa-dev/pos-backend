<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SaleDiscountResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sale_item_id' => $this->sale_item_id,
            'type' => $this->type,
            'percentage' => $this->percentage,
            'amount' => $this->amount,
            'amount_before' => $this->amount_before,
            'amount_after' => $this->amount_after,
            'id_number' => $this->id_number,
            'customer_name' => $this->customer_name,
            'reason' => $this->reason,
        ];
    }
}
