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
            description: "A class's own fields always travel together — one concept masquerading as several fields, guards, and reaches — and should be a single value object",
            rule: "Fields that move as a unit are one type: extract the clump into a value object and hold THAT; never mirror a datum that already lives on a nested object.",
            suggestion: "Fold the co-moving fields into one value object (name the existing type when the clump already is one); drop a field that duplicates a nested object's property.",
        );
    }
}
