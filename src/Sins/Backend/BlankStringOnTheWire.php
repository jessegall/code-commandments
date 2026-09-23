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
            description: 'A `string` field sent over the wire whose TypeScript reader has to check `=== \'\'` to mean "missing" — only that reader knows the blank stands for absence.',
            rule: "A field that crosses the wire says absence in its TYPE; never ship a blank for the far side to decode as missing.",
            suggestion: "`?string \$x = null` on the shape, and the reader asks `x == null` — one spelling of absence, agreed by both sides.",
        );
    }
}
