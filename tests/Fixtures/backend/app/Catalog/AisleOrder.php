<?php

namespace Shop\Catalog;

use JesseGall\CodeCommandments\Sins\Backend\SideTableTrait;
use JesseGall\CodeCommandments\Testing\Righteous;

/**
 * The look-alike the rule is cut back for: a constant naming some of the cases, which the enum
 * WALKS rather than asks about any one of them. It is the order a printed aisle guide runs in,
 * a choice belonging to the guide, not a trait of the aisle — and nothing in the syntax tells
 * those apart, so a constant that is only iterated is left alone.
 */
#[Righteous(SideTableTrait::class)]
enum AisleOrder: string
{
    case Produce = 'produce';
    case Dairy = 'dairy';
    case Bakery = 'bakery';
    case Household = 'household';

    private const PRINTED_GUIDE = [self::Produce, self::Bakery, self::Dairy];

    public static function guide(): array
    {
        $rows = [];
        foreach (self::PRINTED_GUIDE as $aisle) {
            $rows[] = $aisle->value;
        }

        return $rows;
    }
}
