<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Fixtures\Backend\Sinful\StringsThatShouldBeEnumsCrossFile;

/**
 * A generic typed accessor over an OPEN string-keyed bag (issue #7). The
 * `$key` of `asFloat()` is a free-form lookup key — any port name — even
 * though its handful of call sites happen to pass a small set of literals.
 * The frequency heuristic must NOT treat it as an enumerable closed set.
 */
class ValueBag
{
    public function asFloat(string $key, float $default = 0.0): float
    {
        return $default;
    }
}
