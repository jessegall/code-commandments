<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Scribes\Backend;

use JesseGall\CodeCommandments\Ast\NodeMatch;
use JesseGall\CodeCommandments\Scribes\RepentScribe;
use JesseGall\CodeCommandments\Scribes\Span;
use PhpParser\Node\Stmt\If_;

/**
 * Fixes {@see \JesseGall\CodeCommandments\Detectors\Backend\LoopInvertedGuardDetector}: the
 * loop body is one big `if (cond) { … }`, burying the real work a level deep. Invert it to a
 * `continue` guard (`if (! cond) { continue; }`) and hoist the work flat — the inverted-guard
 * fix the skill teaches. The condition is negated with `! (…)` (never a fragile De-Morgan
 * rewrite), so behaviour is identical.
 */
final class LoopInvertedGuardScribe extends RepentScribe
{
    public function rewrite(array $findings): array
    {
        return $this->draft($findings)
            ->replace(fn (NodeMatch $match): ?string => $this->invert($match))
            ->rewrites();
    }

    private function invert(NodeMatch $match): ?string
    {
        $if = $match->node;

        if (! $if instanceof If_ || $if->stmts === []) {
            return null;
        }

        $source = $match->file->source;
        $indent = $match->span()->lineIndent();

        $cond = substr($source, $if->cond->getStartFilePos(), $if->cond->getEndFilePos() + 1 - $if->cond->getStartFilePos());

        $first = $if->stmts[0];
        $last = $if->stmts[array_key_last($if->stmts)];
        $body = new Span($match->file->path, $source, $first->getStartFilePos(), $last->getEndFilePos() + 1)
            ->reindent($indent);

        return "if (! ({$cond})) {\n{$indent}    continue;\n{$indent}}\n\n{$body}";
    }
}
