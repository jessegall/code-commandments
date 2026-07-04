<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\Scope;

/**
 * One condition a file must meet to be a TARGET — a file the run may flag or rewrite. Scopes compound:
 * a {@see Scope} is the AND of its {@see FileScope}s, so a new axis (frozen, a path set, an ownership
 * rule) is added by dropping in another `FileScope`, never by teaching every consumer a new concept.
 */
interface FileScope
{
    public function includes(string $path): bool;
}
