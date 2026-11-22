<?php

namespace Shop\Kiosk;

use JesseGall\CodeCommandments\Sins\Backend\MemberOutOfOrder;
use JesseGall\CodeCommandments\Testing\Righteous;

/**
 * The tiles a kiosk screen shows, counted by hooks that read the field declared above them.
 */
#[Righteous(MemberOutOfOrder::class)]
final class KioskScreen
{
    /**
     * @var list<KioskTile>
     */
    private array $tiles = [];

    protected int $hidden {
        get => count(array_filter($this->tiles, static fn (KioskTile $tile) => $tile->hidden));
    }

    public int $visible {
        get => count($this->tiles) - $this->hidden;
    }
}

final class KioskTile
{
    public function __construct(public readonly bool $hidden) {}
}
