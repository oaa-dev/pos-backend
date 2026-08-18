<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BranchResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'store_id' => $this->store_id,
            'name' => $this->name,
            'code' => $this->code,
            'phone' => $this->phone,
            'status' => $this->status,
            'opened_at' => $this->opened_at?->toDateString(),
            // Callback form, not `new AddressResource($this->whenLoaded('address'))`:
            // a branch with no address yet is loaded-but-null, and the
            // single-argument form drops the key entirely in that case. With a
            // callback the key is present and explicitly null, so `address` is
            // absent only when it was never loaded.
            'address' => $this->whenLoaded('address', fn () => new AddressResource($this->address)),
            'users' => $this->whenLoaded('users', fn () => UserResource::collection($this->users)),
            'users_count' => $this->whenCounted('users'),
            'created_at' => $this->created_at?->format('Y-m-d h:i:s a'),
        ];
    }
}
