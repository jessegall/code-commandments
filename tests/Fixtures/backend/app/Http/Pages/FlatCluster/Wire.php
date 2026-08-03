<?php

namespace Shop\Http\Pages\FlatCluster;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/* The Wire value object: a port's wiring identity, {type, socket, label}. */
#[TypeScript]
final class Wire extends Data
{
    public function __construct(
        public readonly string $type,
        public readonly string $socket,
        public readonly string $label,
    ) {}
}
