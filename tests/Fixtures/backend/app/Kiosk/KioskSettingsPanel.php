<?php

namespace Shop\Kiosk;

use JesseGall\CodeCommandments\Sins\Backend\ConstructorSideEffect;
use JesseGall\CodeCommandments\Testing\Sinful;
use Shop\Support\FeatureGate;

/**
 * Registers a default into shared configuration while being constructed. Building the panel twice
 * writes twice, and building it at all changes what every other reader of that gate sees.
 */
#[Sinful(ConstructorSideEffect::class)]
final class KioskSettingsPanel
{
    private readonly bool $skeletons;

    public function __construct(FeatureGate $gate)
    {
        $gate->override('kiosk.skeletons', true);

        $this->skeletons = $gate->enabled('kiosk.skeletons');
    }

    public function hasSkeletons(): bool
    {
        return $this->skeletons;
    }
}
