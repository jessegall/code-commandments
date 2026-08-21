<?php

namespace Shop\Domain;

use JesseGall\CodeCommandments\Sins\Backend\UselessPropertyHook;

use JesseGall\CodeCommandments\Testing\Fixed;
use JesseGall\CodeCommandments\Testing\Righteous;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * The animated-surface contract — its `{ get; }` is a requirement, NOT a hook to mimic.
 */
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

/**
 * The FIX for {@see TileAnimation}: the same tile with both hooks made STORED properties — the
 * constant body became a property default, the constructed one is assigned ONCE in the constructor.
 * A plain property satisfies the interface's `{ get; }` just as well as a hook did.
 */
#[Fixed(UselessPropertyHook::class)]
final class StoredTileAnimation implements AnimatedTile
{
    public ?string $enterEffect = null;

    public ?string $leaveEffect;

    public function __construct(private readonly string $tileId)
    {
        $this->leaveEffect = implode('+', ['fade', 'morph']);
    }

    public function tileId(): string
    {
        return $this->tileId;
    }
}

/**
 * The righteous twin: every hook here EARNS its syntax — derived from `$this`, or a get/set pair.
 */
#[Righteous(UselessPropertyHook::class)]
final class GlowingTile implements AnimatedTile
{
    private string $easingMode = 'in';

    /**
     * Derived from own state — a real computed property.
     */
    public ?string $enterEffect { get => $this->intensity > 5 ? 'flash' : 'fade'; }

    /**
     * Delegates to own behaviour — still reads the instance.
     */
    public ?string $leaveEffect { get => $this->resolveLeave(); }

    /**
     * A get/set pair is judged as a unit — the setter earns the hook syntax.
     */
    public string $easing {
        get => 'ease-' . $this->easingMode;
        set => strtolower($value);
    }

    public function __construct(private readonly int $intensity) {}

    private function resolveLeave(): ?string
    {
        return $this->intensity > 0 ? 'shrink' : null;
    }
}

/**
 * The driver a tile renders through — one per kind of tile.
 */
interface TileDriver {}

/**
 * The righteous twin the base classes make: a hook reading `static::`, the LATE-bound class. Each
 * subclass names its own driver, so the value is not known where the property is declared and no
 * stored property can express it — the hook is how the base asks "which one are you?".
 */
#[Righteous(UselessPropertyHook::class)]
abstract class RenderedTile
{
    protected const string DRIVER = TileDriver::class;

    protected const string KIND = 'tile';

    public TileDriver $driver { get => new (static::DRIVER)(); }

    public string $kind { get => static::KIND; }
}
