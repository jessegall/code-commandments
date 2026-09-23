<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Frontend\TypeScript;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\TypeScript\Duplication;

final class DuplicateFunction extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'duplicate-typescript-function',
            skill: Duplication::class,
            description: "Copy-pasted code — two+ TypeScript functions (a `function`, a method, a `const` arrow; in a `.ts` module or a component's script) with an identical body, formatting and comments aside",
            rule: "Hoist a function body written twice into one shared function or composable, and call it from both places.",
            suggestion: "Move the body to a shared module or composable under one name, and replace every copy with a call to it.",
        );
    }
}
