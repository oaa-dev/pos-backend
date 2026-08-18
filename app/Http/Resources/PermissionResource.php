<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PermissionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        [$module, $action] = $this->splitName();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'module' => $module,
            'action' => $action,
            'description' => $this->description,
            'status' => $this->status,
        ];
    }

    /**
     * Permission names are `module.action`. Splitting server-side keeps the
     * role editor's grid from re-parsing the same string in several places.
     *
     * @return array{string, string}
     */
    private function splitName(): array
    {
        $parts = explode('.', (string) $this->name, 2);

        return [$parts[0], $parts[1] ?? ''];
    }
}
