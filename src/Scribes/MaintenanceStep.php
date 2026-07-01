<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Scribes;

use JesseGall\CodeCommandments\Ast\Codebase as AstCodebase;
use JesseGall\CodeCommandments\Cli\Scope\Scope;
use JesseGall\CodeCommandments\WorkingCopy;

/**
 * A chain step that runs a self-querying maintenance {@see Scribe} over the PHP AST —
 * Spatie Data hints, redundant arrow-fn return types. In-place edits, no new files.
 */
final class MaintenanceStep implements ScribeStep
{
    public function __construct(private readonly Scribe $scribe) {}

    public function name(): string
    {
        return $this->scribe->name();
    }

    public function run(string|array $path, Scope $scope, WorkingCopy $overlay = new WorkingCopy()): array
    {
        return $this->scribe->rewrites(AstCodebase::scan($path, overlay: $overlay), $scope);
    }
}
