<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Backend;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Backend\Templates;

final class AssembledTemplate extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'assembled-template',
            skill: Templates::class,
            description: "A multi-line template assembled as an array of line fragments and joined with a newline — the output is unreadable in the source that emits it",
            rule: "State a fixed multi-line string as a heredoc, at its real indentation, and interpolate what varies — never as a list of line fragments joined by a newline.",
            suggestion: "A heredoc (`<<<PHP` / `<<<'PHP'`), with the computed part as one interpolation.",
        );
    }
}
