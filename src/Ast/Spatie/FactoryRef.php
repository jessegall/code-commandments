<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Ast\Spatie;

/**
 * The static factory an `array_map(...)` callback invokes per item — the `E::method(...)` a call site maps
 * over a list to build each element ({@see SpatieDataNode::mappedFactory}). Its class, method, resolved
 * return type, and whether the callback reaches beyond its own loop parameter (a captured var / `$this` /
 * an extra bound argument) — a factory that closes over context can't move into a per-item cast.
 */
final readonly class FactoryRef
{
    public function __construct(
        public string $class,
        public string $method,
        public ?string $returnsType,
        public bool $closesOverContext,
    ) {}
}
