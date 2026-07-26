<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Backend;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Backend\Documentation;

final class InlineDocblock extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'inline-docblock',
            skill: Documentation::class,
            description: 'A docblock whose delimiter shares a line with its text — a one-liner, or a block that opens or closes next to content',
            rule: "Write a docblock as a block: the opening delimiter on its own line, one star per line of content, the closing delimiter on its own line.",
            suggestion: "expand it — `repent` does this for you",
        );
    }
}
