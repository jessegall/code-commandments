<?php

namespace Shop\Http\Pages\Hydration;

use JesseGall\CodeCommandments\Sins\Backend\Spatie\NestedTypeMissingTypeScript;
use JesseGall\CodeCommandments\Testing\Righteous;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/*
 * Righteous twin for NestedTypeMissingTypeScript: a `#[TypeScript]` Data whose nested `Priority` is an
 * ENUM with NO `#[TypeScript]`. The transformer's enum collector auto-generates a type for any enum it
 * meets, so an untagged nested enum is never `undefined` — this must NOT flag.
 */
#[Righteous(NestedTypeMissingTypeScript::class)]
#[TypeScript]
final class EnumSlotRighteous extends Data
{
    public function __construct(
        public readonly string $subject,
        public readonly Priority $priority,
    ) {}
}

enum Priority: string
{
    case Low = 'low';
    case High = 'high';
}
