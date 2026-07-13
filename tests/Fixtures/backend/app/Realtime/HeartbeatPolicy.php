<?php

namespace Shop\Realtime;

use JesseGall\CodeCommandments\Sins\Backend\UselessPropertyHook;

use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * A block-bodied hook is no more earned than an arrow one when it still reads no state:
 * both of these are constants dressed as computed slots — plain property defaults.
 */
#[Sinful(UselessPropertyHook::class)]
final class HeartbeatPolicy
{
    public int $intervalMs {
        get {
            return 15000;
        }
    }

    public array $backoffSteps {
        get {
            return [1, 2, 5, 10];
        }
    }
}
