<?php

namespace Shop\Kiosk;

use JesseGall\CodeCommandments\Sins\Backend\ComputedBooleanArgument;
use JesseGall\CodeCommandments\Testing\Fixed;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * Picks how far the kiosk chrome tucks into the screen corners.
 */
final class CornerInset
{
    /**
     * Told, not asked: the editor is what this is ABOUT, yet the signature names only the answer.
     * Every caller has to remember which of the editor's modes count — and the one that forgets
     * the panel leaves the corners tucked in mid-mode.
     */
    #[Sinful(ComputedBooleanArgument::class)]
    public static function of(bool $tucked): string
    {
        return $tucked ? 'tight' : 'wide';
    }

    /**
     * The FIX: take the SUBJECT the callers already hold. `CornerInset::for($editor)` asks the editor
     * itself, so the rule about which modes tuck the corners in lives here once — no call site holds a
     * half-remembered copy of it, and none can forget the panel.
     */
    #[Fixed(ComputedBooleanArgument::class)]
    public static function for(KioskEditor $editor): string
    {
        return $editor->inZenMode() || $editor->hasPanelOpen() ? 'tight' : 'wide';
    }
}
