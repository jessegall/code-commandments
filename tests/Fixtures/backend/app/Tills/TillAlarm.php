<?php

namespace Shop\Tills;

use Closure;
use JesseGall\CodeCommandments\Sins\Backend\NullableCallback;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * Sounds a till's alarm when the drawer is forced, telling a guard when one was handed in.
 */
final class TillAlarm
{
    #[Sinful(NullableCallback::class)]
    public function __construct(
        private readonly string $till,
        private readonly Closure | null $notifyGuard = null,
    ) {}

    public function sound(): string
    {
        if ($this->notifyGuard) {
            ($this->notifyGuard)($this->till);
        }

        return "alarm at {$this->till}";
    }
}
