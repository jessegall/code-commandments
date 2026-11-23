<?php

namespace Shop\Shipping;

use JesseGall\CodeCommandments\Sins\Backend\Laravel\ConfigRead;
use JesseGall\CodeCommandments\Testing\Righteous;

/**
 * The dispatch settings as one typed object, read from the config once and injected wherever they are needed.
 */
#[Righteous(ConfigRead::class)]
final class DispatchSettings
{
    public readonly int $cutoffHour;

    public function __construct()
    {
        $this->cutoffHour = (int) config('dispatch.cutoff_hour');
    }

    public function closesBefore(int $hour): bool
    {
        return $hour < $this->cutoffHour;
    }
}
