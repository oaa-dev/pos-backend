<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SalePaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'method' => $this->method,
            'amount' => $this->amount,
            'reference_no' => $this->reference_no,
        ];
    }
}
