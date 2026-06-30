<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Scribes;

use JesseGall\CodeCommandments\Cli\Scope\Scope;

/**
 * One link in the {@see ScribeChain} — a single rewriting pass `repent` runs. Each
 * step SCANS the codebase fresh and returns the files it would change, so steps run in
 * sequence each see the previous one's edits. A step has a stable {@see name} so the
 * chain can reorder, replace or remove it by that name (the Laravel-middleware move).
 */
interface ScribeStep
{
    /**
     * The step's stable name — what the chain reorders / replaces it by.
     */
    public function name(): string;

    /**
     * The files this step changes, from a fresh scan of $path.
     *
     * @return array<string, string>  path => new content
     */
    public function run(string $path, Scope $scope): array;
}
