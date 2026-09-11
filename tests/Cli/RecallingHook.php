<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Cli;

use JesseGall\CodeCommandments\Hooks\Hook;
use JesseGall\CodeCommandments\Hooks\HookBinding;
use JesseGall\CodeCommandments\Hooks\HookEvent;

/**
 * A stand-in for a hook that re-surfaces something on a compaction — two of them on one SessionStart
 * are what the dispatcher must merge into a single context.
 */
abstract class RecallingHook extends Hook
{
    abstract protected function recall(): string;

    public function bindings(): array
    {
        return [new HookBinding('SessionStart')];
    }

    protected function onSessionStart(HookEvent $event): int
    {
        return $event->source() === 'compact' ? $this->inject($event, $this->recall()) : $this->pass();
    }
}
