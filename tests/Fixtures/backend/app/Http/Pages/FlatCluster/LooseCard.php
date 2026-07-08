<?php

namespace Shop\Http\Pages\FlatCluster;

use JesseGall\CodeCommandments\Sins\Backend\Spatie\FlatFieldCluster;
use JesseGall\CodeCommandments\Testing\Righteous;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/*
 * Righteous twin: meta{Title,Count} share a prefix, but NO `Meta` value object exists — the flat fields
 * restate nothing the codebase models, so there is no sub-object to nest into. Must NOT flag.
 */
#[TypeScript]
#[Righteous(FlatFieldCluster::class)]
final class LooseCard extends Data
{
    public function __construct(
        public readonly string $metaTitle,
        public readonly int $metaCount,
    ) {}
}
