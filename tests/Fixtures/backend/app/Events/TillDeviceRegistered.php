<?php

namespace Shop\Events;

use JesseGall\CodeCommandments\Sins\Backend\NamespaceCycle;
use JesseGall\CodeCommandments\Testing\Righteous;
use Shop\Models\TillDevice;

/**
 * Announces that a device was registered to a till.
 */
#[Righteous(NamespaceCycle::class)]
final class TillDeviceRegistered
{
    public function __construct(public readonly TillDevice $device) {}
}
