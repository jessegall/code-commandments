<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\Scope;

use JesseGall\CodeCommandments\Workspace;

/**
 * The `--repent=ID|latest` scope: restrict to the files a previous judge run reported,
 * by reading that run's checklist. So `judge`/`repent --repent=latest` act on exactly
 * what the last run found — no scope to recompute. `latest` is the session's `sins.md`;
 * an ID is its archive (`sins-<id>.md`).
 */
final class ChecklistScope implements ChangeScope
{
    public function __construct(
        private readonly string $id,
        private readonly Workspace $workspace,
    ) {}

    public function restrictTo(string $path): ?array
    {
        $checklist = $this->id === 'latest'
            ? $this->workspace->path('sins.md')
            : $this->workspace->path("sins-{$this->id}.md");

        if (! is_file($checklist)) {
            throw ScopeUnavailable::noChecklist($this->id, $checklist);
        }

        // Each finding line carries a `path:line` token — collect the distinct files,
        // keyed by path (the shape Scope canonicalizes).
        preg_match_all('/`([^`]+):\d+`/', (string) file_get_contents($checklist), $matches);

        return array_fill_keys(array_values(array_unique($matches[1])), true);
    }
}
