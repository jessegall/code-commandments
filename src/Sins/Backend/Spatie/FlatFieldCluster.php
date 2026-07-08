<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Backend\Spatie;

use JesseGall\CodeCommandments\Sins\RequiresComposerPackage;
use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Backend\ValueObjects;

/**
 * A flat-root smell: a `#[TypeScript]` `Data` class spreads a value object it ALREADY MODELS flat across
 * sibling scalar fields sharing a camelCase prefix — `wireType`/`wireLabel` instead of `wire: Wire{type,
 * label}`. Precision-first: it fires only when a class named for the prefix exists, so the flat fields
 * provably restate a real sub-object; references (`…Id`), boolean flags, and function-word prefixes
 * (`total`/`close`/`is`) are excluded.
 */
final class FlatFieldCluster extends Sin implements RequiresComposerPackage
{
    use RequiresSpatieData;

    public function __construct()
    {
        parent::__construct(
            name: 'flat-field-cluster',
            skill: ValueObjects::class,
            description: "A `#[TypeScript]` `Data` class spreads a value object it already models flat across sibling scalar fields sharing a camelCase prefix (`wireType` + `wireLabel`) instead of NESTING the existing `Wire{type, label}` — width instead of depth",
            rule: "When scalar fields on a Data class share a prefix that names a value object the codebase already declares, they restate that object flat. Nest them into the existing sub-object and shed the prefix — `wireType`/`wireLabel` become `wire: Wire{type, label}`.",
            suggestion: "Replace the prefixed siblings with a single nested property typed as the existing value object, dropping the prefix from each member.",
        );
    }
}
