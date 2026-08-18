<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CashDrawerSessionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'branch_id' => $this->branch_id,
            'user_id' => $this->user_id,
            'opened_at' => $this->opened_at?->format('Y-m-d h:i:s a'),
            'opening_float' => $this->opening_float,
            'closed_at' => $this->closed_at?->format('Y-m-d h:i:s a'),
            'closing_counted' => $this->closing_counted,
            'expected_cash' => $this->expected_cash,
            'variance' => $this->variance,
            'status' => $this->status,
            'closing_notes' => $this->closing_notes,
            'branch' => $this->whenLoaded('branch', fn () => new BranchResource($this->branch)),
            'user' => $this->whenLoaded('user', fn () => new UserResource($this->user)),
        ];
    }
}
