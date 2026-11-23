<?php

namespace Shop\Models;

use Illuminate\Database\Eloquent\Model;
use JesseGall\CodeCommandments\Sins\Backend\NamespaceCycle;
use JesseGall\CodeCommandments\Testing\Righteous;
use Shop\Events\TillDeviceRegistered;

/**
 * A card reader or scanner registered to a till; registering one is announced to the shop.
 */
#[Righteous(NamespaceCycle::class)]
final class TillDevice extends Model
{
    protected static function booted(): void
    {
        static::created(static function (self $device): void {
            event(new TillDeviceRegistered($device));
        });
    }
}
