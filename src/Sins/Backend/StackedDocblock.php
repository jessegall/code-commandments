<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Backend;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Backend\Documentation;

final class StackedDocblock extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'stacked-docblock',
            skill: Documentation::class,
            description: 'Two or more docblocks stacked on one declaration — PHP reads only the last, so the ones above it are documentation nobody sees',
            rule: "One declaration carries ONE docblock — merge a stack into a single block, because the language hands only the last one to a reader's tooling.",
            suggestion: "merge them into one block — `repent` does this for you",
        );
    }
}
