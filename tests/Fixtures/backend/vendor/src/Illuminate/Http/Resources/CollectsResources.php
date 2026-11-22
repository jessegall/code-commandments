<?php

namespace Illuminate\Http\Resources;

trait CollectsResources
{
    /** @return mixed */
    protected function collectResource($resource) {}
    /** @return class-string<\Illuminate\Http\Resources\Json\JsonResource>|null */
    protected function collects() {}
    /** @return int */
    public function jsonOptions() {}
    public function getIterator(): \Traversable {}
}
