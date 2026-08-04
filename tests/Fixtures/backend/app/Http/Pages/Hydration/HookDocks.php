<?php

namespace Shop\Http\Pages\Hydration;

use JesseGall\CodeCommandments\Sins\Backend\Spatie\HookMissingComputed;
use JesseGall\CodeCommandments\Testing\Fixed;
use JesseGall\CodeCommandments\Testing\Sinful;
use Spatie\LaravelData\Attributes\Computed;
use Spatie\LaravelData\Data;

/*
 * Scenario 1 — a get-only hook projecting a collaborator's list, no `#[Computed]`. Spatie reads `docks`
 * as a hydration input and expects it in `::from()`, which a get-only hook cannot receive.
 */
#[Sinful(HookMissingComputed::class)]
final class DockShell extends Data
{
    public array $docks { get => $this->dockSet->all(); }

    public function __construct(
        public readonly DockSet $dockSet,
        public readonly string $side,
    ) {}

    public function isEmpty(): bool
    {
        return $this->dockSet->all() === [];
    }

    public function onSide(string $side): bool
    {
        return $this->side === $side;
    }
}

/**
 * The FIX for {@see DockShell}: the get-only hook is stamped `#[Computed]`, so Spatie treats `docks`
 * as an OUTPUT-only value it derives after hydration — it is no longer expected in `::from()`.
 */
#[Fixed(HookMissingComputed::class)]
final class ComputedDockShell extends Data
{
    #[Computed]
    public array $docks { get => $this->dockSet->all(); }

    public function __construct(
        public readonly DockSet $dockSet,
        public readonly string $side,
    ) {}

    public function facing(): string
    {
        return $this->side === 'port' ? 'starboard' : 'port';
    }
}
