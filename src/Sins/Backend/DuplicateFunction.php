<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Backend;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Backend\FixAtTheSource;

final class DuplicateFunction extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'duplicate-function',
            skill: FixAtTheSource::class,
            description: "Copy-pasted code — two+ functions with an identical AST (formatting/comments aside)",
            rule: "Extract copy-pasted code — two functions with an identical AST must become one."
        );
    }
}
