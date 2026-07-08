<?php

namespace Shop\Http\Pages\Hydration;

use JesseGall\CodeCommandments\Sins\Backend\Spatie\NestedTypeMissingTypeScript;
use JesseGall\CodeCommandments\Sins\Backend\Spatie\NullableWireObject;
use JesseGall\CodeCommandments\Testing\Righteous;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/*
 * Righteous twin for NullableWireObject — the nested object already uses `| Optional` (omitted from the
 * wire when absent), and the nullable field that remains is a SCALAR (an explicit null can be meaningful).
 * Also righteous for NestedTypeMissingTypeScript: its nested `BannerIcon` carries `#[TypeScript]`. Must NOT flag.
 */
#[Righteous(NullableWireObject::class)]
#[Righteous(NestedTypeMissingTypeScript::class)]
#[TypeScript]
final class WireBanner extends Data
{
    public function __construct(
        public readonly string $id,
        public readonly BannerIcon|Optional $icon = new Optional(),
        public readonly string|null $caption = null,
    ) {}
}

#[TypeScript]
final class BannerIcon extends Data
{
    public function __construct(public readonly string $name = '') {}
}
