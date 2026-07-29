<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Scribes\Backend;

use JesseGall\CodeCommandments\Ast\NodeMatch;
use JesseGall\CodeCommandments\Ast\Support\Negation;
use JesseGall\CodeCommandments\Scribes\RepentScribe;
use JesseGall\CodeCommandments\Scribes\Span;
use PhpParser\Node\Stmt\If_;

/**
 * Fixes {@see \JesseGall\CodeCommandments\Detectors\Backend\LoopInvertedGuardDetector}: the
 * loop body is one big `if (cond) { … }`, burying the real work a level deep. Invert it to a
 * `continue` guard (`if (! cond) { continue; }`) and hoist the work flat — the inverted-guard
 * fix the skill teaches. The condition is flipped by {@see Negation} (a `!` in front, parenthesised
 * only where that changes meaning — never a fragile De-Morgan rewrite), so behaviour is identical,
 * and the guard opens its block in the file's own brace style ({@see Span::blockOpener}) — a fix a
 * human has to reformat is only half a fix (#416).
 */
final class LoopInvertedGuardScribe extends RepentScribe
{
    public function rewrite(array $findings): array
    {
        return $this->draft($findings)
            ->replace(fn (NodeMatch $match) => $this->invert($match))
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

        $guard = Negation::of($if->cond, $source);
        $opener = Span::blockOpener($source, $if->cond->getEndFilePos(), $indent); // The style of THIS
        // very `if` — the guard that replaces it opens its block exactly the same way.

        $first = $if->stmts[0];
        $last = $if->stmts[array_key_last($if->stmts)];
        $body = new Span($match->file->path, $source, $first->getStartFilePos(), $last->getEndFilePos() + 1)
            ->reindent($indent);

        return "if ({$guard}){$opener}\n{$indent}    continue;\n{$indent}}\n\n{$body}";
    }
}
