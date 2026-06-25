<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

// Sin: Missing #[TypeScript] attribute on API Resource
class UserResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
        ];
    }
}
