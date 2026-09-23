<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\CSharp;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\CSharp\Duplication;

final class NearDuplicateMethod extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'near-duplicate-csharp-method',
            skill: Duplication::class,
            description: 'A near-copy — two+ C# methods, accessors or local functions with one control-flow skeleton that differ only in their local names or the literals they use (a key, a route, a message)',
            rule: 'Merge two methods that differ only in a literal into one, and pass what differs as a parameter.',
            suggestion: 'Name the literal that differs, make it a parameter of one shared method, and call that from both places.',
        );
    }
}
