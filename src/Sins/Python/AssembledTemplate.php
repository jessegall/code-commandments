<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\Python;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\Python\Templates;

final class AssembledTemplate extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'python-assembled-template',
            skill: Templates::class,
            description: 'a multi-line string built as a list of line fragments and `"\n".join(...)`-ed, instead of a triple-quoted f-string that shows its output',
            rule: 'Write a multi-line string as one triple-quoted f-string (dedented) that shows its output, never a list of line fragments joined with a newline.',
            suggestion: 'Replace the list and the join with `dedent(f"""…""")`, the varying parts as `{placeholders}` where they land.',
        );
    }
}
