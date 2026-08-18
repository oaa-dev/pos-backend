<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SupplierResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'store_id' => $this->store_id,
            'name' => $this->name,
            'contact_person' => $this->contact_person,
            'phone' => $this->phone,
            'email' => $this->email,

            'notes' => $this->notes,
            'status' => $this->status,
            // Callback form: a supplier with no address is loaded-but-null.
            'address' => $this->whenLoaded('address', fn () => new AddressResource($this->address)),
            'created_at' => $this->created_at?->format('Y-m-d h:i:s a'),
        ];
    }
}
