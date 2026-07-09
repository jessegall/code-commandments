<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\Scope;

use JesseGall\CodeCommandments\Config;
use JesseGall\CodeCommandments\ExcludedPaths;

/**
 * The scope of files NOT under a project's {@see Config::exclude} paths — compounded into a run's
 * {@see Scope} so an excluded subtree is never a target. The sibling of {@see FrozenScope}: Frozen
 * excludes a file by its in-source marker, this excludes it by a config-declared path prefix. The
 * matching lives once in {@see ExcludedPaths}.
 */
final class ExcludedScope implements FileScope
{
    public function __construct(private readonly ExcludedPaths $excluded) {}

    /**
     * The excluded scope for a project rooted at $root — reads its `config.php` exclude() list.
     */
    public static function forProject(string $root): self
    {
        return new self(ExcludedPaths::under($root, Config::load($root)->excludedPaths()));
    }

    public function includes(string $path): bool
    {
        return ! $this->excluded->covers($path);
    }
}
