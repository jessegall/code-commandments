<?php

namespace Shop\Kiosk;

use JesseGall\CodeCommandments\Sins\Backend\ComputedBooleanArgument;
use JesseGall\CodeCommandments\Testing\Righteous;

/**
 * The kiosk's on-screen editor, and the only thing that knows what mode it is in.
 */
final class KioskEditor
{
    public function __construct(
        private readonly bool $zen,
        private readonly bool $panel,
    ) {}

    public function inZenMode(): bool
    {
        return $this->zen;
    }

    public function hasPanelOpen(): bool
    {
        return $this->panel;
    }

    /**
     * The chooser that ASKS instead of being told. The rule about corners lives here, once, with
     * the object it is about — so no caller can hold a half-remembered copy of it.
     */
    #[Righteous(ComputedBooleanArgument::class)]
    public function cornerInset(): string
    {
        return $this->inZenMode() || $this->hasPanelOpen() ? 'tight' : 'wide';
    }
}
