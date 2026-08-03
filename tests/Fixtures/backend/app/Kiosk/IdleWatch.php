<?php

namespace Shop\Kiosk;

use JesseGall\CodeCommandments\Sins\Backend\ShortCircuitStatement;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * Puts an idle kiosk to sleep. Both sinful shapes are chains — several conditions strung
 * together with the work hanging off the end, one in `&&` and one in the low-precedence
 * `and` spelling. The righteous twin (`tickGuarded`) states the same chain as one `if`.
 */
final class IdleWatch
{
    private bool $asleep = false;

    #[Sinful(ShortCircuitStatement::class)]
    public function tick(int $idleSeconds, bool $unlocked): void
    {
        $unlocked && $idleSeconds > 300 && ! $this->asleep && $this->sleep();
    }

    #[Sinful(ShortCircuitStatement::class)]
    public function wake(bool $touched): void
    {
        $touched and $this->asleep and $this->asleep = false;
    }

    public function tickGuarded(int $idleSeconds, bool $unlocked): void
    {
        if ($unlocked && $idleSeconds > 300 && ! $this->asleep) {
            $this->sleep();
        }
    }

    private function sleep(): void
    {
        $this->asleep = true;
    }
}
