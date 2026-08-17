<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Backend;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Backend\Absence;

final class BlankStringDefault extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'blank-string-default',
            skill: Absence::class,
            description: "`string \$x = ''` standing in for absence — then asked `\$x === ''`",
            rule: "Model a value that may not be there in the type; never default a total `string` to `''` and read that blank back as \"missing\".",
            suggestion: "Say it in the type — `?string \$x = null`, or an `Option<string>` — so the blank is not a value the reader has to decode.",
        );
    }
}
