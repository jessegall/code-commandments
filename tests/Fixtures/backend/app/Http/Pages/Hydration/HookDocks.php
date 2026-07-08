<?php

namespace Shop\Http\Pages\Hydration;

use JesseGall\CodeCommandments\Sins\Backend\Spatie\HookMissingComputed;
use JesseGall\CodeCommandments\Testing\Sinful;
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
