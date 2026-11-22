<?php

namespace Shop\Lockers;

use JesseGall\CodeCommandments\Sins\Backend\UnusedPrivateMember;
use JesseGall\CodeCommandments\Testing\Sinful;

/**
 * A pickup locker at one site, holding the codes of the parcels placed in it.
 */
final class Locker
{
    #[Sinful(UnusedPrivateMember::class)]
    private array $parcels = [];

    public function __construct(
        public readonly string $site,
        public LockerMode $mode,
    ) {}

    public function place(string $parcelCode): void
    {
        $this->parcels[] = $parcelCode;
    }
}
