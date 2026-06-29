<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\Scope;

/**
 * Which files a judge run reports on. A strategy chosen by a `match` on the judge
 * flags: the whole codebase by default, or — with `--git` / `--branch` — only the
 * files you've touched. The whole path is always parsed (cross-file detectors need
 * the full graph); a scope only narrows which findings are shown.
 */
interface ChangeScope
{
    /**
     * The absolute file paths to restrict findings to (`path => true`), or null to
     * mean "no restriction — report on the whole codebase".
     *
     * @return array<string, true>|null
     *
     * @throws ScopeUnavailable when the scope cannot be resolved (not a git
     *                          repository, or an unknown base ref).
     */
    public function restrictTo(string $path): ?array;
}
