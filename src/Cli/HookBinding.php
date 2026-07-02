<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli;

/**
 * One place a {@see Hook} binds into Claude Code's settings: the event it fires on and the tool
 * matcher that scopes it (null ⇒ every call of the event). A hook declares its bindings the way a
 * detector declares its sin, so {@see Hooks} wires the settings straight from the hook classes —
 * built-in or consumer-registered — with no central table to maintain.
 */
final readonly class HookBinding
{
    public function __construct(
        public string $event,
        public ?string $matcher = null,
    ) {}
}
