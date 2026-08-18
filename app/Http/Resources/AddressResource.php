<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AddressResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'region_id' => $this->region_id,
            'province_id' => $this->province_id,
            'city_id' => $this->city_id,
            'barangay_id' => $this->barangay_id,
            'address_line' => $this->address_line,
            'postal_code' => $this->postal_code,
            'type' => $this->type,
            'is_default' => $this->is_default,
            'region' => new RegionResource($this->whenLoaded('region')),
            'province' => new ProvinceResource($this->whenLoaded('province')),
            'city' => new CityResource($this->whenLoaded('city')),
            'barangay' => new BarangayResource($this->whenLoaded('barangay')),
        ];
    }
}
