<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Scribes;

/**
 * What a repent run settled on: the final content of every file it would change, and the rewriters
 * that could not run. A rewriter that broke wrote nothing, which reads exactly like one that had
 * nothing to fix — so the two are told apart here rather than left to the reader.
 */
final class Converged
{
    /**
     * @param  array<string, string>  $files  path => final content
     * @param  list<string>  $skipped  the rewriters that broke, named
     */
    public function __construct(public readonly array $files = [], public readonly array $skipped = []) {}
}
