<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SaleSuspensionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $expiresAt = $this->expiresAt();

        return [
            'id' => $this->id,
            'branch_id' => $this->branch_id,
            'label' => $this->label,
            'payload' => $this->payload,
            'item_count' => count($this->payload['items'] ?? []),
            'status' => $this->status,
            'suspended_at' => $this->suspended_at?->format('Y-m-d h:i:s a'),
            'expires_at' => $expiresAt?->toIso8601String(),
            'is_expired' => $this->isExpired(),
            'seconds_remaining' => $expiresAt
                ? max($expiresAt->getTimestamp() - now()->getTimestamp(), 0)
                : 0,
            'resumed_at' => $this->resumed_at?->format('Y-m-d h:i:s a'),
            'user' => $this->whenLoaded('user', fn () => new UserResource($this->user)),
        ];
    }
}
