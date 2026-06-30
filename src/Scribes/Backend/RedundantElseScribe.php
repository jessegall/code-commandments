<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Scribes\Backend;

use JesseGall\CodeCommandments\Ast\NodeMatch;
use JesseGall\CodeCommandments\Scribes\RepentScribe;
use JesseGall\CodeCommandments\Scribes\Span;
use PhpParser\Node\Stmt\If_;

/**
 * Fixes {@see \JesseGall\CodeCommandments\Detectors\Backend\RedundantElseDetector}: the
 * `if`-branch already exits (return/throw/continue/break), so the `else` is dead weight.
 * Keep the guard verbatim and HOIST the `else` body out after it, dedented one level — the
 * exact `else`-drop the redundant-else skill teaches.
 */
final class RedundantElseScribe extends RepentScribe
{
    public function rewrite(array $findings): array
    {
        return $this->draft($findings)
            ->replace(fn (NodeMatch $match): ?string => $this->unwrap($match))
            ->rewrites();
    }

    private function unwrap(NodeMatch $match): ?string
    {
        $if = $match->node;

        if (! $if instanceof If_ || $if->else === null) {
            return null;
        }

        $source = $match->file->source;

        // The guard `if (cond) { … }` verbatim — everything up to the `else` keyword, with
        // the whitespace between the closing `}` and `else` trimmed off.
        $guard = rtrim(substr($source, $if->getStartFilePos(), $if->else->getStartFilePos() - $if->getStartFilePos()));

        if ($if->else->stmts === []) {
            return $guard;
        }

        // The else body, lifted to the guard's indentation (dedented one level).
        $first = $if->else->stmts[0];
        $last = $if->else->stmts[array_key_last($if->else->stmts)];

        $body = new Span($match->file->path, $source, $first->getStartFilePos(), $last->getEndFilePos() + 1)
            ->reindent($match->span()->lineIndent());

        return "{$guard}\n\n{$body}";
    }
}
