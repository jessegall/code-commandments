<?php

namespace Illuminate\Support\Traits;

trait TransformsToResourceCollection
{
    public function toResourceCollection(?string $resourceClass = NULL): \Illuminate\Http\Resources\Json\ResourceCollection {}
    protected function guessResourceCollection(): \Illuminate\Http\Resources\Json\ResourceCollection {}
    protected function resolveResourceFromAttribute(string $class): ?string {}
    protected function resolveResourceCollectionFromAttribute(string $class): ?string {}
}
