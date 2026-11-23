<?php

namespace Shop\Lockers;

use JesseGall\CodeCommandments\Sins\Backend\ManufacturedFakeFill;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * Tells a courier whether the locker their scanner read is one this depot knows.
 */
final class LockerScan
{
    public function __construct(private readonly LockerFinder $finder) {}

    /**
     * @param  array<string, string>  $scan
     */
    #[Sinful(ManufacturedFakeFill::class)]
    public function known(array $scan): bool
    {
        return $this->finder->find((int) ($scan['locker'] ?? 0)) !== null;
    }
}
