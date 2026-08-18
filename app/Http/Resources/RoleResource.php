<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RoleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'is_system' => $this->is_system,
            'status' => $this->status,
            'permissions_count' => $this->whenCounted('permissions'),
            'permissions' => PermissionResource::collection($this->whenLoaded('permissions')),
            'created_at' => $this->created_at?->format('Y-m-d h:i:s a'),
        ];
    }
}
