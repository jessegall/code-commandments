<?php

namespace Shop\Kiosk;

use JesseGall\CodeCommandments\Sins\Backend\ComputedBooleanArgument;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * The chrome around the kiosk canvas — the caller that remembers BOTH editor modes.
 */
final class KioskChrome
{
    #[Sinful(ComputedBooleanArgument::class)]
    public function frame(KioskEditor $editor): string
    {
        return 'frame:' . CornerInset::of($editor->inZenMode() || $editor->hasPanelOpen());
    }
}
