<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'firstname' => $this->firstname,
            'lastname' => $this->lastname,
            'middlename' => $this->middlename,
            'suffix' => $this->suffix,
            'salutation' => $this->salutation,
            'gender' => $this->gender,
            'birthdate' => $this->birthdate?->toDateString(),
            'photo_url' => $this->profile_photo_path
                ? $request->getSchemeAndHttpHost().'/storage/'.ltrim($this->profile_photo_path, '/')
                : null,
            'user' => new UserResource($this->whenLoaded('user')),
            'address' => new AddressResource($this->whenLoaded('address')),
        ];
    }
}
