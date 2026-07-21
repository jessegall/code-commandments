<?php

namespace Shop\Kiosk;

use JesseGall\CodeCommandments\Sins\Backend\Laravel\DanglingRouteName;
use JesseGall\CodeCommandments\Testing\Righteous;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * A redirect names a route in the kiosk group. `kiosk.lobby` was never opened — and a redirect is the
 * worst place for a dangling name, because the failure lands on a COLD path nobody exercises until a
 * customer does.
 */
final class KioskRedirects
{
    #[Sinful(DanglingRouteName::class)]
    public function afterScan(bool $recognised): object
    {
        if ($recognised) {
            return redirect()->route('kiosk.home');
        }

        return redirect()->route('kiosk.lobby');
    }

    #[Righteous(DanglingRouteName::class)]
    public function afterCheckout(): object
    {
        return to_route('kiosk.home');
    }
}
