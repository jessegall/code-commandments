<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\Orchestration;

/**
 * The switch that holds automatic dispatch off. Triggers, the queue and the scheduler are parked, and
 * every entry point asks here rather than keeping its own opinion about whether it may fire.
 */
final readonly class Parked
{
    /**
     * Automatic dispatch. Turning it back on is a deliberate act in a diff, never a state a session
     * drifts into.
     */
    public const bool DISPATCH = true;
}
