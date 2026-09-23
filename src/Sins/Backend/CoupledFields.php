<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Backend;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Backend\ValueObjects;

/**
 * Sibling of the shipped {@see DataClump} (which finds a param clump recurring ACROSS classes); this finds
 * VALUE fields WITHIN one class that are really one object — coupled (co-assembled/guarded together),
 * cross-object same-type peers, or a field that redundantly mirrors a nested object's property.
 */
final class CoupledFields extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'coupled-fields',
            skill: ValueObjects::class,
            description: 'A class\'s own fields always change and get checked together — one concept split across several fields — and should be folded into a single value object.',
            rule: 'Fields that always change together are really one type — extract them into a value object and use it directly; don\'t keep a field that just duplicates a nested object\'s property.',
            suggestion: "Fold the co-moving fields into one value object (name the existing type when the clump already is one); drop a field that duplicates a nested object's property.",
        );
    }
}
