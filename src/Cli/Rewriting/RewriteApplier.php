<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Cli\Rewriting;

/**
 * Writes a rewriter's `path => newContent` map to disk — the "execute" half of a
 * rewrite, kept off the pure {@see Rewriter} (mirrors how DetectorRunner executes a
 * Detector). Returns the paths it wrote.
 */
final class RewriteApplier
{
    /**
     * @param  array<string, string>  $rewrites  path => new content
     * @return list<string>  the paths written
     */
    public function apply(array $rewrites): array
    {
        foreach ($rewrites as $path => $content) {
            file_put_contents($path, $content);
        }

        return array_keys($rewrites);
    }
}
