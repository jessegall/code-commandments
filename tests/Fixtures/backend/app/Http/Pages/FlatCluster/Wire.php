<?php

namespace Shop\Http\Pages\FlatCluster;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;
use JesseGall\CodeCommandments\Sins\Backend\Spatie\FlatFieldCluster;
use JesseGall\CodeCommandments\Testing\Fixed;

/* The Wire value object: a port's wiring identity, {type, socket, label}. */
#[TypeScript]
#[Fixed(FlatFieldCluster::class)]
final class Wire extends Data
{
    public function __construct(
        public readonly string $type,
        public readonly string $socket,
        public readonly string $label,
    ) {}
}
