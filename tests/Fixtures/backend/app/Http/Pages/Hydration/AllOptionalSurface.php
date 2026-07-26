<?php

namespace Shop\Http\Pages\Hydration;

use JesseGall\CodeCommandments\Sins\Backend\Spatie\AllOptionalData;
use JesseGall\CodeCommandments\Testing\Sinful;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

/*
 * Scenario 2 — a visual surface style bag, all string/float leaves Optional. Different types and a
 * CSS-variable projection distinguish it from the grid box; the smell is the same all-optional envelope.
 */
#[Sinful(AllOptionalData::class)]
final class SurfaceStyle extends Data
{
    public function __construct(
        public readonly string|Optional $background = new Optional(),
        public readonly string|Optional $border = new Optional(),
        public readonly float|Optional $radius = new Optional(),
    ) {}

    /**
     * @return array<string, string>
     */
    public function cssVars(): array
    {
        $vars = [];

        if (! $this->background instanceof Optional) {
            $vars['--surface-bg'] = $this->background;
        }

        if (! $this->border instanceof Optional) {
            $vars['--surface-border'] = $this->border;
        }

        if (! $this->radius instanceof Optional) {
            $vars['--surface-radius'] = $this->radius . 'px';
        }

        return $vars;
    }

    public function inlineStyle(): string
    {
        $pairs = [];

        foreach ($this->cssVars() as $name => $value) {
            $pairs[] = "{$name}: {$value}";
        }

        return implode('; ', $pairs);
    }
}
