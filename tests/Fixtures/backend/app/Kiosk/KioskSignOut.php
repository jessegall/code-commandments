<?php

namespace Shop\Kiosk;

use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Redirector;
use JesseGall\CodeCommandments\Sins\Backend\Laravel\HardCodedRoutePath;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * Ends a kiosk session and sends the screen back to where a new customer starts.
 */
final class KioskSignOut
{
    public function __construct(private readonly Redirector $redirect) {}

    #[Sinful(HardCodedRoutePath::class)]
    public function leave(KioskSession $session): RedirectResponse
    {
        $target = $session->isSpent() ? '/kiosk/home' : '/dashboard';

        return $this->redirect->to($target);
    }
}
