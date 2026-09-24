<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\CSharp;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\CSharp\Documentation;

final class DanglingDocReference extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'csharp-dangling-doc-reference',
            skill: Documentation::class,
            description: 'a `<see cref>` that resolves to nothing from where it is written — a name the project no longer declares, or one spelled so it does not reach it',
            rule: 'A `cref` must resolve: name what the code is called now, spelled so it reaches it from here, or delete it.',
            suggestion: 'Point the `cref` at what the name became, qualified or imported so it resolves here (the compiler warns CS1574 until it does); if nothing replaced it, drop the reference.',
        );
    }
}
