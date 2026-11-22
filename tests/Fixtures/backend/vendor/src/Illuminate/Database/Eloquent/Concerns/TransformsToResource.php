<?php

namespace Illuminate\Database\Eloquent\Concerns;

trait TransformsToResource
{
    public function toResource(?string $resourceClass = NULL): \Illuminate\Http\Resources\Json\JsonResource {}
    protected function guessResource(): \Illuminate\Http\Resources\Json\JsonResource {}
    public static function guessResourceName(): array {}
    protected function resolveResourceFromAttribute(string $class): ?string {}
}
