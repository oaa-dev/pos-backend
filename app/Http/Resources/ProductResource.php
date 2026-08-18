<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'store_id' => $this->store_id,
            'name' => $this->name,
            'sku' => $this->sku,
            'category_id' => $this->category_id,
            'base_unit_id' => $this->base_unit_id,
            'is_perishable' => $this->is_perishable,
            'is_favorite' => $this->is_favorite,
            'favorite_sort' => $this->favorite_sort,
            'status' => $this->status,
            'image_path' => $this->image_path,
            'image_url' => $this->image_path === null
                ? null
                : $request->getSchemeAndHttpHost().'/storage/'.ltrim($this->image_path, '/'),
            // Callback form throughout: a product with no category is
            // loaded-but-null, and the single-argument form drops the key
            // entirely in that case.
            'category' => $this->whenLoaded('category', fn () => new CategoryResource($this->category)),
            'base_unit' => $this->whenLoaded('baseUnit', fn () => new UnitResource($this->baseUnit)),
            'units' => $this->whenLoaded('units', fn () => ProductUnitResource::collection($this->units)),
            'created_at' => $this->created_at?->format('Y-m-d h:i:s a'),
        ];
    }
}
