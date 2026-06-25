<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Contracts;

use JesseGall\CodeCommandments\Support\CallGraph\CodebaseIndex;

/**
 * A prophet that benefits from a scroll-wide call graph.
 *
 * ScrollManager builds a `CodebaseIndex` once per scroll run and hands it
 * to every prophet that implements this interface before iterating files.
 * Prophets that don't implement it are untouched.
 */
interface NeedsCodebaseIndex
{
    public function setCodebaseIndex(CodebaseIndex $index): void;
}
