<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Sins\CSharp;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Skills\CSharp\Templates;

final class AssembledTemplate extends Sin
{
    public function __construct()
    {
        parent::__construct(
            name: 'csharp-assembled-template',
            skill: Templates::class,
            description: '`string.Join("\n", new[] { "public class X", "{", "}" })` or `sb.AppendLine("…")` line after line — a multi-line text built from line fragments, so its shape cannot be seen in the source',
            rule: 'Write a multi-line text as a raw string literal that shows its output, not as lines joined with a newline.',
            suggestion: 'Replace the join with `$"""` … `"""`, the lines written as they will appear and the values in `{placeholders}`.',
        );
    }
}
