<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CityResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'psgc_code' => $this->psgc_code,
            'name' => $this->name,
            'is_city' => $this->is_city,
            'region_id' => $this->region_id,
            'province_id' => $this->province_id,
            'region' => new RegionResource($this->whenLoaded('region')),
            'province' => new ProvinceResource($this->whenLoaded('province')),
            'barangays' => BarangayResource::collection($this->whenLoaded('barangays')),
        ];
    }
}
