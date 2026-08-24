<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Support;

/**
 * The one way an edge is added to a `from => to => true` graph: an unnamed side and a self-edge are
 * dropped and a target is normalised, so every graph in the package answers the same about them.
 */
final class EdgeMap
{
    /**
     * @param  array<string, array<string, true>>  $edges
     */
    public static function link(array &$edges, ?string $from, ?string $target): void
    {
        if ($from === null || $target === null || $from === $target) {
            return;
        }

        $edges[$from][ltrim($target, '\\')] = true;
    }
}
