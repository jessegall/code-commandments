<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Scribes;

use JesseGall\CodeCommandments\Cli\Scope\Scope;
use JesseGall\CodeCommandments\WorkingCopy;

/**
 * One link in the {@see ScribeChain} — a single rewriting pass `repent` runs. Each
 * step SCANS the codebase fresh, reading THROUGH the run's {@see WorkingCopy} overlay so
 * it sees every earlier step's (and earlier sweep's) edits, and returns the files it
 * would change. A step has a stable {@see name} so the chain can reorder, replace or
 * remove it by that name (the Laravel-middleware move).
 */
interface ScribeStep
{
    /**
     * The step's stable name — what the chain reorders / replaces it by.
     */
    public function name(): string;

    /**
     * The files this step changes, from a fresh scan of $path read through $overlay.
     *
     * @return array<string, string>  path => new content
     */
    public function run(string $path, Scope $scope, WorkingCopy $overlay = new WorkingCopy()): array;
}
