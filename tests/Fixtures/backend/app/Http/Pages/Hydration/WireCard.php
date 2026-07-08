<?php

namespace Shop\Http\Pages\Hydration;

use JesseGall\CodeCommandments\Sins\Backend\Spatie\NestedTypeMissingTypeScript;
use JesseGall\CodeCommandments\Sins\Backend\Spatie\NullableWireObject;
use JesseGall\CodeCommandments\Testing\Sinful;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/*
 * Scenario 1 — a frontend-bound breadcrumb trail. Its optional overflow menu is a nested Data typed
 * `| null`; the wire ships `"overflow": null` where `Overflow | Optional` would omit the key.
 */
#[Sinful(NullableWireObject::class)]
#[Sinful(NestedTypeMissingTypeScript::class)]
#[TypeScript]
final class WireCard extends Data
{
    /** @param list<string> $segments */
    public function __construct(
        public readonly array $segments,
        public readonly string $separator = '/',
        public readonly Overflow|null $overflow = null,
    ) {}

    public function path(): string
    {
        return implode($this->separator, $this->segments);
    }

    public function leaf(): string
    {
        $last = end($this->segments);

        return $last === false ? '' : $last;
    }

    public function depth(): int
    {
        return count($this->segments) + ($this->overflow === null ? 0 : $this->overflow->hiddenCount);
    }
}

final class Overflow extends Data
{
    public function __construct(public readonly int $hiddenCount = 0) {}
}
