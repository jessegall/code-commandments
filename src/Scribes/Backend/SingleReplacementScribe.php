<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Scribes\Backend;

use JesseGall\CodeCommandments\Ast\NodeMatch;
use JesseGall\CodeCommandments\Scribes\RepentScribe;

/**
 * The shape shared by every scribe whose whole fix is "replace each finding's span with a computed string,
 * or skip it when null" — the redundant-else / native-cast / enum-unwrap / nested-from repenters. The
 * template `rewrite` drives the {@see \JesseGall\CodeCommandments\Scribes\Draft} once; a concrete scribe
 * supplies only {@see replacement}, the one thing that genuinely differs.
 */
abstract class SingleReplacementScribe extends RepentScribe
{
    public function rewrite(array $findings): array
    {
        return $this->draft($findings)
            ->replace(fn (NodeMatch $match): ?string => $this->replacement($match))
            ->rewrites();
    }

    /**
     * The text to replace $match's span with, or null to leave it untouched.
     */
    abstract protected function replacement(NodeMatch $match): ?string;
}
