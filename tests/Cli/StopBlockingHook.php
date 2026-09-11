<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Cli;

use JesseGall\CodeCommandments\Hooks\Hook;
use JesseGall\CodeCommandments\Hooks\HookBinding;
use JesseGall\CodeCommandments\Hooks\HookEvent;

/**
 * A stand-in for a consumer's hook that HOLDS every stop to say "keep going" — what the dispatcher's
 * own Stop rules (parked on background work, plan mode) are proven against, without a handler that
 * needs state. It opts out of speaking while work pends, as a keep-going nudge does.
 */
final class StopBlockingHook extends Hook
{
    public function bindings(): array
    {
        return [new HookBinding('Stop')];
    }

    protected function speaksWhileWorkPends(): bool
    {
        return false;
    }

    protected function onStop(HookEvent $event): int
    {
        return $this->block('the work is not finished');
    }
}
