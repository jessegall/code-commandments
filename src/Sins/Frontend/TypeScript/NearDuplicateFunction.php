<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Frontend\TypeScript;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\TypeScript\Duplication;

final class NearDuplicateFunction extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'near-duplicate-typescript-function',
            skill: Duplication::class,
            description: "A near-copy — two+ TypeScript functions with one control-flow skeleton that differ only in their local names or the literals they use (an endpoint, a key, a label)",
            rule: "Merge two functions that differ only in a literal into one, and pass what differs as a parameter.",
            suggestion: "Name the literal that differs, make it a parameter of one shared function, and call that from both places.",
        );
    }
}
