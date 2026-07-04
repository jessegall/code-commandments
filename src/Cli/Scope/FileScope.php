<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\Scope;

/**
 * One condition a file must meet to be a TARGET — a file the run may flag or rewrite. Scopes compound: a
 * {@see Scope} is the AND of its {@see FileScope}s, so a new axis is added by dropping in another one.
 */
interface FileScope
{
    public function includes(string $path): bool;
}
