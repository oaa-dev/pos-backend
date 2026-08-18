<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'store_id' => $this->store_id,
            'name' => $this->name,
            'nickname' => $this->nickname,
            'phone' => $this->phone,
            'branch_id' => $this->branch_id,
            'current_balance' => $this->current_balance,
            'is_blocked' => $this->is_blocked,
            'notes' => $this->notes,
            'branch' => $this->whenLoaded('branch', fn () => new BranchResource($this->branch)),
            'created_at' => $this->created_at?->format('Y-m-d h:i:s a'),
        ];
    }
}
