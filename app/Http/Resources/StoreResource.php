<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The tenant, as the shop owner sees it.
 *
 * `deleted_at` is deliberately absent: a store reads its own record through
 * `GET /store`, and whether the SaaS operator has soft-deleted it is not a
 * fact for the customer's sidebar.
 */
class StoreResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'slug' => $this->slug,
            'owner_name' => $this->owner_name,
            'phone' => $this->phone,
            'logo_url' => $this->logo_path === null
                ? null
                : $request->getSchemeAndHttpHost().'/storage/'.ltrim($this->logo_path, '/'),
            'status' => $this->status,
            // Callback form, and it matters more here than usual: a store
            // opened without an owner is the normal state on the management
            // screen, and the bare form would drop the key on exactly the rows
            // the screen exists to flag.
            'owner' => $this->whenLoaded('owner', fn () => new UserResource($this->owner)),
            'created_at' => $this->created_at?->format('Y-m-d h:i:s a'),
        ];
    }
}
