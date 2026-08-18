<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Relations use the callback form of `whenLoaded`: the single-argument form
 * drops the key entirely when a relation is loaded but null, and null is the
 * normal case here — a deleted user, a global action with no store. The
 * frontend cannot tell "absent because unloaded" from "absent because null".
 */
class ActivityLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'action' => $this->action,
            'reason' => $this->reason,
            'old_values' => $this->old_values,
            'new_values' => $this->new_values,
            'device' => $this->device,
            'ip_address' => $this->ip_address,

            // The subject as a type and id rather than a loaded model: the
            // morph spans a dozen classes with no common resource, and the
            // screen only needs to name what was touched.
            'subject_type' => $this->auditable_type ? class_basename($this->auditable_type) : null,
            'subject_id' => $this->auditable_id,

            'user_id' => $this->user_id,
            'store_id' => $this->store_id,
            'causer' => $this->whenLoaded('causer', fn () => [
                'id' => $this->causer?->id,
                'name' => $this->causer?->name,
                'email' => $this->causer?->email,
            ]),
            'store' => $this->whenLoaded('store', fn () => [
                'id' => $this->store?->id,
                'name' => $this->store?->name,
            ]),
            'created_at' => $this->created_at?->format('Y-m-d h:i:s a'),
        ];
    }
}
