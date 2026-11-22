<?php

namespace Illuminate\Database\Concerns;

trait CompilesJsonPaths
{
    /** @return array */
    protected function wrapJsonFieldAndPath($column) {}
    /** @return string */
    protected function wrapJsonPath($value, $delimiter = '->') {}
    /** @return string */
    protected function wrapJsonPathSegment($segment) {}
}
