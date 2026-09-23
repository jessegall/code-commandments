<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Python;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Python\Absence;

final class BlankStringDefault extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'python-blank-string-default',
            skill: Absence::class,
            description: '`x: str = ""` standing in for absence — then asked `x == ""`, `not x` or `if x:` in its own scope',
            rule: 'Say a value may be missing in its type; never default a `str` to `""` and read that blank back as "missing".',
            suggestion: '`x: str | None = None`, asked `x is None` — so the blank is not a value every reader has to decode.',
        );
    }
}
