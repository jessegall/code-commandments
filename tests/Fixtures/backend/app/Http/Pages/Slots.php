<?php

namespace Shop\Http\Pages;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The small nested Data a page object composes its payload from — leaf DTOs, righteous for every
 * detector (final, readonly, honest scalar types). They ride back on `#[TypeScript]` pages, so they carry
 * `#[TypeScript]` too — else they'd generate as `undefined` on the frontend.
 */
#[TypeScript]
final class StatCard extends Data
{
    public function __construct(
        public readonly string $label,
        public readonly string $value,
    ) {}
}

#[TypeScript]
final class MenuLink extends Data
{
    public function __construct(
        public readonly string $label,
        public readonly string $href,
    ) {}
}

#[TypeScript]
final class CartLine extends Data
{
    public function __construct(
        public readonly string $sku,
        public readonly int $qty,
    ) {}
}
