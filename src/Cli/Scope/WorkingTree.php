<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\Scope;

/**
 * What one reading of a checkout's status says: the commit it stands on, and the judged files changed or
 * created on top of it.
 */
final readonly class WorkingTree
{
    /**
     * @param  string  $head  the commit HEAD names — empty in a repository with no commit yet
     * @param  array<string, true>  $changed  absolute paths, deletions excluded
     */
    public function __construct(
        public string $head,
        public array $changed,
    ) {}
}
