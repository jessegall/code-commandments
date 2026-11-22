<?php

namespace Illuminate\Database\Concerns;

trait CompilesJsonPaths
{
    protected function wrapJsonFieldAndPath($column) {}
    protected function wrapJsonPath($value, $delimiter = '->') {}
    protected function wrapJsonPathSegment($segment) {}
}
