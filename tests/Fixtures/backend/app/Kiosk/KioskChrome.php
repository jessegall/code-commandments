<?php

namespace Shop\Kiosk;

/**
 * The chrome around the kiosk canvas — the caller that remembers BOTH editor modes.
 */
final class KioskChrome
{
    public function frame(KioskEditor $editor): string
    {
        return 'frame:' . CornerInset::of($editor->inZenMode() || $editor->hasPanelOpen());
    }
}
