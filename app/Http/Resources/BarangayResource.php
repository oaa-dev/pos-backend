<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BarangayResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'psgc_code' => $this->psgc_code,
            'name' => $this->name,
            'city_id' => $this->city_id,
            'city' => new CityResource($this->whenLoaded('city')),
        ];
    }
}
