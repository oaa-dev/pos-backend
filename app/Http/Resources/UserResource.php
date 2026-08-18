<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone_number' => $this->phone_number,
            // The callback form matters: `whenLoaded('x')` on a loaded-but-null
            // relation yields null, which gets filtered out and drops the key
            // entirely. With a callback it emits an explicit null instead, so
            // the key is absent only when the relation was never loaded.
            'profile' => $this->whenLoaded('profile', fn () => new UserProfileResource($this->profile)),
            'role' => $this->whenLoaded('role', fn () => new RoleResource($this->role)),
            'store' => $this->whenLoaded('store', fn () => new StoreResource($this->store)),
            'status' => $this->status,
            'created_at' => $this->created_at?->format('Y-m-d h:i:s a'),
        ];
    }
}
