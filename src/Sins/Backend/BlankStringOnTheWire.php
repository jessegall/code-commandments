<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Backend;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Backend\Absence;

final class BlankStringOnTheWire extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'blank-string-on-the-wire',
            skill: Absence::class,
            description: "a total `string` field whose TypeScript reader — holding this very type — asks it `=== ''`: the blank means \"missing\", and only the far side says so",
            rule: "A field that crosses the wire says absence in its TYPE; never ship a blank for the far side to decode as missing.",
            suggestion: "`?string \$x = null` on the shape, and the reader asks `x == null` — one spelling of absence, agreed by both sides.",
        );
    }
}
