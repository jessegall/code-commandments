<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Backend;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Backend\Documentation;

final class BloatedDocblock extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'bloated-docblock',
            skill: Documentation::class,
            description: "Multi-paragraph class docblock (class too big)",
            rule: "Keep a class docblock to one tight paragraph — a multi-paragraph essay means the class does too much."
        );
    }
}
