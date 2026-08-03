<?php

namespace Shop\Http\Pages\FlatCluster;

use JesseGall\CodeCommandments\Sins\Backend\Spatie\FlatFieldCluster;
use JesseGall\CodeCommandments\Testing\Fixed;
use JesseGall\CodeCommandments\Testing\Sinful;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/*
 * PortView restates the existing Wire value object FLAT as wire{Type,Socket,Label} instead of nesting a
 * single `wire: Wire`. The flat trio travels to the frontend and should be one depth-nested sub-object.
 */
#[TypeScript]
#[Sinful(FlatFieldCluster::class)]
final class PortView extends Data
{
    public function __construct(
        public readonly string $wireType,
        public readonly string $wireSocket,
        public readonly string $wireLabel,
        public readonly int $index,
    ) {}

    public function slot(): string
    {
        return $this->wireSocket . '#' . $this->index;
    }

    public function isBus(): bool
    {
        return $this->wireType === 'bus' || $this->wireType === 'backplane';
    }

    public function ordinal(): string
    {
        return match (true) {
            $this->index === 0 => 'primary',
            $this->index < 4 => 'secondary',
            default => 'auxiliary',
        };
    }

    public function pinout(string $prefix): string
    {
        return strtoupper($prefix) . '/' . $this->wireLabel . '/' . $this->slot();
    }
}

/**
 * The same view with its depth restored: the wire{Type,Socket,Label} trio is NESTED as the one `Wire` the
 * codebase already declares, so each member sheds the prefix and the value object is modelled once.
 */
#[TypeScript]
#[Fixed(FlatFieldCluster::class)]
final class NestedPortView extends Data
{
    public function __construct(
        public readonly Wire $wire,
        public readonly int $index,
    ) {}
}
