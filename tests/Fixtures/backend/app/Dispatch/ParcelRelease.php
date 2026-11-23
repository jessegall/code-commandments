<?php

namespace Shop\Dispatch;

use JesseGall\CodeCommandments\Sins\Backend\FeatureEnvy;
use JesseGall\CodeCommandments\Sins\Backend\FilledParameter;
use JesseGall\CodeCommandments\Testing\Righteous;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * Releases a held parcel: its hold is cleared, and the caller confirms nothing holds it any more.
 */
final class ParcelRelease
{
    public function release(HeldParcel $parcel): bool
    {
        $this->clearHold($parcel);

        return $parcel->holdReason === null;
    }

    #[Righteous(FilledParameter::class)]
    #[Sinful(FeatureEnvy::class)]
    private function clearHold(HeldParcel $parcel): void
    {
        $parcel->holdReason = null;
    }
}
