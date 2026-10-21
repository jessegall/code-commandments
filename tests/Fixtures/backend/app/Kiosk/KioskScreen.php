<?php

namespace Shop\Kiosk;

use JesseGall\CodeCommandments\Sins\Backend\ImpossibleGuard;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * The screen a customer stands at, which the till asks about while they are there. The guard
 * below answers "no minutes left" for a session the type says is always there, so the till can
 * never tell a real zero from a session nobody passed.
 */
final class KioskScreen
{
    private const IDLE = 0;

    #[Sinful(ImpossibleGuard::class)]
    public function minutesLeft(KioskSession $session): int
    {
        if ($session === null) {
            return self::IDLE;
        }

        return $session->minutes;
    }

    public function isIdle(KioskSession $session): bool
    {
        return $this->minutesLeft($session) === self::IDLE;
    }
}
