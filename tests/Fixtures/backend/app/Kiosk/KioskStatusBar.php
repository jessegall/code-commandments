<?php

namespace Shop\Kiosk;

/**
 * The status strip along the bottom — the caller that has drifted: it never heard about panels,
 * so its corners disagree with the chrome's the moment one opens.
 */
final class KioskStatusBar
{
    public function strip(KioskEditor $editor): string
    {
        return 'strip:' . CornerInset::of($editor->inZenMode());
    }
}
