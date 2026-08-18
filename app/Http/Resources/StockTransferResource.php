<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The index used to return raw models straight from the controller. This is the
 * envelope the list screen reads.
 *
 * Relations use the callback form of `whenLoaded`: the single-argument form
 * drops the key entirely when a relation is loaded but null, which the frontend
 * cannot tell apart from "not requested".
 */
class StockTransferResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'transfer_number' => $this->transfer_number,
            'from_branch_id' => $this->from_branch_id,
            'to_branch_id' => $this->to_branch_id,
            'status' => $this->status,
            'notes' => $this->notes,
            'sent_at' => $this->sent_at?->format('Y-m-d h:i:s a'),
            'received_at' => $this->received_at?->format('Y-m-d h:i:s a'),
            'items_count' => $this->whenCounted('items'),
            'from_branch' => $this->whenLoaded('fromBranch', fn () => new BranchResource($this->fromBranch)),
            'to_branch' => $this->whenLoaded('toBranch', fn () => new BranchResource($this->toBranch)),
            'items' => $this->whenLoaded('items', fn () => StockTransferItemResource::collection($this->items)),
            'created_at' => $this->created_at?->format('Y-m-d h:i:s a'),
        ];
    }
}
