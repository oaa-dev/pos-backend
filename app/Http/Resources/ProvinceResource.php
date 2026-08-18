<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProvinceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'psgc_code' => $this->psgc_code,
            'name' => $this->name,
            'region_id' => $this->region_id,
            'region' => new RegionResource($this->whenLoaded('region')),
            'cities' => CityResource::collection($this->whenLoaded('cities')),
        ];
    }
}
