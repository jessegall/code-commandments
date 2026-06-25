<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

// Righteous: Has #[TypeScript] attribute for type generation
#[TypeScript]
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
