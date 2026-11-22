<?php

namespace Illuminate\Http\Resources;

trait CollectsResources
{
    protected function collectResource($resource) {}
    protected function collects() {}
    public function jsonOptions() {}
    public function getIterator(): \Traversable {}
}
