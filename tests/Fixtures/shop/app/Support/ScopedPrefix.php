<?php

namespace Shop\Support;

/**
 * Righteous twin (NOT scratch state): this save/restore brackets a callable it
 * was HANDED. You can't thread a parameter into a closure's transitive callees,
 * so setting the field for the duration of `$body()` is the dynamic-scope /
 * Context pattern, not a smuggled input. The detector must leave this alone.
 */
final class ScopedPrefix
{
    private string $prefix = '';

    public function within(string $segment, callable $body): void
    {
        $parent = $this->prefix;
        $this->prefix = $segment;

        try {
            $body();
        } finally {
            $this->prefix = $parent;
        }
    }
}
