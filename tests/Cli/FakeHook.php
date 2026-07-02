<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Cli;

use JesseGall\CodeCommandments\Cli\Hook;
use JesseGall\CodeCommandments\Cli\HookBinding;

/**
 * A stand-in for a consumer's own registered {@see Hook} — binds to a distinct event so wiring it
 * alongside the built-ins is unambiguous.
 */
final class FakeHook extends Hook
{
    public function bindings(): array
    {
        return [new HookBinding('Notification')];
    }
}
