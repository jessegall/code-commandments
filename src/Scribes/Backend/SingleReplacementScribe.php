<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Scribes\Backend;

use JesseGall\CodeCommandments\Ast\NodeMatch;
use JesseGall\CodeCommandments\Scribes\RepentScribe;
use JesseGall\CodeCommandments\Scribes\Writer;
use PhpParser\Node;

/**
 * The shape shared by every scribe whose whole fix is "replace each finding with a computed string,
 * or skip it when null" — the redundant-else / native-cast / enum-unwrap / nested-from repenters, and
 * the statement-unfolders. The template `rewrite` drives the
 * {@see \JesseGall\CodeCommandments\Scribes\Draft} once; a concrete scribe supplies only
 * {@see replacement}, the one thing that genuinely differs — and, where the fix must land on more than
 * the found node, {@see target}.
 */
abstract class SingleReplacementScribe extends RepentScribe
{
    public function rewrite(array $findings): array
    {
        $draft = $this->draft([]);

        foreach ($findings as $match) {
            if (! $match instanceof NodeMatch) {
                continue;
            }

            $replacement = $this->replacement($match);

            if ($replacement !== null) {
                Writer::for($draft, $match)->replace($this->target($match), $replacement);
            }
        }

        return $draft->rewrites();
    }

    /**
     * The text to replace $match with, or null to leave it untouched.
     */
    abstract protected function replacement(NodeMatch $match): ?string;

    /**
     * WHICH node the replacement lands on — the finding itself, unless a scribe says otherwise. An
     * expression standing alone as a STATEMENT overrides this with the statement, so the fix swallows
     * the trailing `;` instead of leaving one dangling. Only ever consulted for a finding
     * {@see replacement} has already agreed to rewrite.
     */
    protected function target(NodeMatch $match): Node
    {
        return $match->node;
    }
}
