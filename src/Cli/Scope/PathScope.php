<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\Scope;

/**
 * Only files under one directory are TARGETS — the counterweight to parsing wide. A run given a path
 * must still SEE the whole project, or a cross-file question answers against a partial world ("does
 * anything extend this class" comes back `no` because the subclass lives in a sibling root, and a
 * rewrite that seals the class on that answer emits PHP that will not load, #483), so the parse covers
 * every source root and this scope keeps the writing where the user pointed.
 */
final class PathScope implements FileScope
{
    private readonly string $root;

    public function __construct(string $root)
    {
        $this->root = rtrim(realpath($root) ?: $root, '/');
    }

    public function includes(string $path): bool
    {
        $real = realpath($path) ?: $path;

        return $real === $this->root || str_starts_with($real, $this->root . '/');
    }
}
