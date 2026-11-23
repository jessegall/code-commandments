<?php

namespace Shop\Dispatch;

use JesseGall\CodeCommandments\Sins\Backend\Laravel\ConfigRead;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * Says where a held parcel can be collected, read from the dispatch config on every call.
 */
final class HeldParcelNotifier
{
    #[Sinful(ConfigRead::class)]
    public function collectionNote(HeldParcel $parcel): string
    {
        return ($parcel->holdReason ?? 'held') . ', collect before ' . config('dispatch.cutoff_hour') . ':00';
    }
}
