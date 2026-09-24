<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\CSharp;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\CSharp\Enums;

final class MatchDefaultReturnsNull extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'csharp-match-default-returns-null',
            skill: Enums::class,
            description: 'a `switch` that names every member of an enum, then answers `null`, `default` or `false` in its `_` arm — the one value that arm can see is a bug, and it is answered as if it were fine',
            rule: 'When a switch names every member of an enum, make its `_` arm throw.',
            suggestion: 'Write `_ => throw new ArgumentOutOfRangeException(nameof(status), status, null)`.',
        );
    }
}
