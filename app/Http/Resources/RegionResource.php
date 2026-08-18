<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RegionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'psgc_code' => $this->psgc_code,
            'name' => $this->name,
            'provinces' => ProvinceResource::collection($this->whenLoaded('provinces')),
            'cities' => CityResource::collection($this->whenLoaded('cities')),
        ];
    }
}
