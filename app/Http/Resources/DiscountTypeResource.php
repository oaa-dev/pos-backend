<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DiscountTypeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'percentage' => $this->percentage,
            'requires_identification' => $this->requires_identification,
            // Tells the UI to render the row read-only rather than letting a
            // user discover the 422 by hitting save.
            'is_system' => $this->is_system,
            'status' => $this->status,
            'created_at' => $this->created_at?->format('Y-m-d h:i:s a'),
        ];
    }
}
