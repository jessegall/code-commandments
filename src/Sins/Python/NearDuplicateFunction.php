<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Python;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Python\Duplication;

final class NearDuplicateFunction extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'near-duplicate-python-function',
            skill: Duplication::class,
            description: 'A near-copy — two+ Python functions or methods with one control-flow skeleton that differ only in their local names or the literals they use (a path, a key, a message)',
            rule: 'Merge two functions that differ only in a literal into one, and pass what differs as a parameter.',
            suggestion: 'Name the literal that differs, make it a parameter of one shared function, and call that from both places.',
        );
    }
}
