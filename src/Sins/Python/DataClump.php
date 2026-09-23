<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Python;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Python\ValueObjects;

final class DataClump extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'python-data-clump',
            skill: ValueObjects::class,
            description: 'The same three or more scalar parameters (`street: str, city: str, postcode: str`) threaded through functions in two or more classes or modules — one concept wearing no name',
            rule: 'Give values that always travel together one type, and pass that instead of the loose values.',
            suggestion: 'Declare a frozen dataclass with those fields and take it as one parameter wherever the loose values travelled together.',
        );
    }
}
