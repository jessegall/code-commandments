<?php

namespace Shop\Http\Pages\Hydration;

use Spatie\LaravelData\Data;

/** A small value Data an optional-map producer fixture hydrates. */
final class OptTag extends Data
{
    public function __construct(public readonly string $label = '') {}
}
