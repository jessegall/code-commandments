<?php

namespace Shop\Kiosk;

use JesseGall\CodeCommandments\Sins\Backend\HandRolledRegistry;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * The screens a kiosk can show, filed under a lower-cased slug and opened by it while the session lasts.
 */
#[Sinful(HandRolledRegistry::class)]
final class ScreenRack
{
    private array $screens = [];

    public function mount(string $slug, KioskScreen $screen): void
    {
        $this->screens[strtolower($slug)] = $screen;
    }

    public function open(string $slug, KioskSession $session): ?KioskScreen
    {
        if (! isset($this->screens[strtolower($slug)])) {
            throw ScreenMissing::forSlug($slug);
        }

        return $session->isSpent() ? null : $this->screens[strtolower($slug)];
    }
}
