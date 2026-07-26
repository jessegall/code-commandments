<?php

namespace Shop\Http\Pages\Hydration;

use JesseGall\CodeCommandments\Sins\Backend\Spatie\NestedTypeMissingTypeScript;
use JesseGall\CodeCommandments\Sins\Backend\Spatie\NullableWireObject;
use JesseGall\CodeCommandments\Testing\Sinful;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/*
 * Scenario 3 — a frontend-bound inspector panel. Its optional header group is a nested Data typed `| null`,
 * rendered through CSS-class composition unlike the price/weight scenarios.
 */
#[Sinful(NullableWireObject::class)]
#[Sinful(NestedTypeMissingTypeScript::class)]
#[TypeScript]
final class WirePanel extends Data
{
    /**
     * @param array<string, string> $tokens
     */
    public function __construct(
        public readonly PanelHeader|null $header = null,
        public readonly string $variant = 'plain',
        public readonly bool $bordered = true,
        public readonly array $tokens = [],
    ) {}

    public function classAttribute(): string
    {
        $classes = ['insp-panel', "insp-{$this->variant}"];

        if ($this->bordered) {
            $classes[] = 'insp-bordered';
        }

        return implode(' ', $classes);
    }

    public function styleVars(): string
    {
        $pairs = [];

        foreach ($this->tokens as $name => $value) {
            $pairs[] = "--{$name}: {$value}";
        }

        return implode('; ', $pairs);
    }
}

final class PanelHeader extends Data
{
    public function __construct(public readonly string $text = '', public readonly bool $sticky = false) {}
}
