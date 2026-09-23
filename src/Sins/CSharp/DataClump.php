<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\CSharp;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\CSharp\ValueObjects;

final class DataClump extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'csharp-data-clump',
            skill: ValueObjects::class,
            description: 'The same three or more string, number, date or id parameters threaded through methods of two or more types — values that always travel together but have no type of their own.',
            rule: 'Bundle values that always travel together into one type — a record — instead of threading them side by side.',
            suggestion: 'Name the clump as a record (a `readonly record struct` when it is small), build it once where the values meet, and pass that instead of the separate parameters.',
        );
    }
}
