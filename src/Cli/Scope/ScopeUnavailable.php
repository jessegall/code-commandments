<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\Scope;

use RuntimeException;

/**
 * A `--changes`/`--branch` scope that could not be resolved. One factory per cause, so each
 * sentence lives beside the condition that produces it; printed to STDERR, exit non-zero.
 */
final class ScopeUnavailable extends RuntimeException
{
    public static function notAGitRepository(string $path): self
    {
        return new self("Not a git repository (or git unavailable): {$path}");
    }

    public static function baseRefMissing(string $base, string $path): self
    {
        return new self("Base ref '{$base}' not found: {$path}");
    }

    public static function noChecklist(string $id, string $looked): self
    {
        return new self("No checklist for --repent={$id} (looked for {$looked}).");
    }
}
