<?php

namespace Shop\Shipping;

use JesseGall\CodeCommandments\Sins\Backend\SideTableTrait;
use JesseGall\CodeCommandments\Testing\Fixed;
use JesseGall\CodeCommandments\Testing\Righteous;

/**
 * The same trait, answered by the case instead of listed beside it: a window added tomorrow cannot
 * be written without saying whether it is extended, because the match has no case to fall through to.
 */
#[Fixed(SideTableTrait::class)]
#[Righteous(SideTableTrait::class)]
enum ReturnWindow: string
{
    case Fortnight = 'fortnight';
    case Month = 'month';
    case Quarter = 'quarter';

    public function isExtended(): bool
    {
        return match ($this) {
            self::Fortnight => false,
            self::Month, self::Quarter => true,
        };
    }
}
