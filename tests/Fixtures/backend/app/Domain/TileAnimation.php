<?php

namespace Shop\Domain;

use JesseGall\CodeCommandments\Sins\Backend\UselessPropertyHook;

use JesseGall\CodeCommandments\Testing\Righteous;
use JesseGall\CodeCommandments\Testing\Sinful;

/** The animated-surface contract — its `{ get; }` is a requirement, NOT a hook to mimic. */
interface AnimatedTile
{
    public ?string $enterEffect { get; }

    public ?string $leaveEffect { get; }
}

/**
 * Implements the contract by copying the interface's hook syntax — but neither body reads
 * `$this`, so both are stored properties wearing computed syntax: the constant belongs in a
 * property default, the constructed value in the constructor.
 */
#[Sinful(UselessPropertyHook::class)]
final class TileAnimation implements AnimatedTile
{
    public ?string $enterEffect { get => null; }

    public ?string $leaveEffect {
        get => implode('+', ['fade', 'morph']);
    }

    public function __construct(private readonly string $tileId) {}
}

/** The righteous twin: every hook here EARNS its syntax — derived from `$this`, or a get/set pair. */
#[Righteous(UselessPropertyHook::class)]
final class GlowingTile implements AnimatedTile
{
    /** Derived from own state — a real computed property. */
    public ?string $enterEffect { get => $this->intensity > 5 ? 'flash' : 'fade'; }

    /** Delegates to own behaviour — still reads the instance. */
    public ?string $leaveEffect { get => $this->resolveLeave(); }

    /** A get/set pair is judged as a unit — the setter earns the hook syntax. */
    public string $easing {
        get => 'ease-' . $this->easingMode;
        set => strtolower($value);
    }

    private string $easingMode = 'in';

    public function __construct(private readonly int $intensity) {}

    private function resolveLeave(): ?string
    {
        return $this->intensity > 0 ? 'shrink' : null;
    }
}
