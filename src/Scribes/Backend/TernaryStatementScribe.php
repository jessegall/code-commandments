<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Scribes\Backend;

use JesseGall\CodeCommandments\Ast\NodeMatch;
use JesseGall\CodeCommandments\Ast\Support\Negation;
use JesseGall\CodeCommandments\Scribes\Span;
use JesseGall\CodeCommandments\Scribes\Writer;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Ternary;
use PhpParser\Node\Stmt\Expression;

/**
 * Fixes {@see \JesseGall\CodeCommandments\Detectors\Backend\TernaryStatementDetector}: a ternary
 * standing as a statement is choosing an ACTION, so it is spelled out as the `if`/`else` it already
 * is — the condition as written, each arm its own statement. The SHORT form `$a ?: $b;` has no
 * second arm to keep (its "then" IS the condition, and re-emitting that would evaluate it twice),
 * so it becomes a single `if` on the flipped condition ({@see Negation}) — exactly what
 * {@see ShortCircuitStatementScribe} makes of the `||` it is a synonym for. The whole statement is
 * replaced (see {@see target}), the blocks open in the file's own brace style
 * ({@see NodeMatch::controlBlockOpener}), the `else` stands where that file stands it, and each arm
 * is re-indented one level in (#416).
 */
final class TernaryStatementScribe extends SingleReplacementScribe
{
    protected function replacement(NodeMatch $match): ?string
    {
        $ternary = $match->node;

        if (! $ternary instanceof Ternary || ! $match->parent()->node instanceof Expression) {
            return null;
        }

        $indent = $match->span()->lineIndent();
        $opener = $match->controlBlockOpener($indent);

        // `$a ?: $b` yields $a itself when it holds, so there is no "then" branch to write —
        // only the else, under the flipped condition.
        if ($ternary->if === null) {
            $unless = Negation::of($ternary->cond, $match->file->source);

            return "if ({$unless}){$opener}\n{$this->arm($match, $ternary->else, $indent)};\n{$indent}}";
        }

        $condition = Writer::slice($match->file->source, $ternary->cond);
        $then = $this->arm($match, $ternary->if, $indent);
        $else = $this->arm($match, $ternary->else, $indent);

        // A file that stands its braces on their own line stands the `else` on one too.
        $otherwise = $match->controlBracesOnOwnLine() ? "{$indent}}\n{$indent}else" : "{$indent}} else";

        return "if ({$condition}){$opener}\n{$then};\n{$otherwise}{$opener}\n{$else};\n{$indent}}";
    }

    protected function target(NodeMatch $match): Node
    {
        return $match->parent()->node;
    }

    /**
     * One arm as the body of its block — its source, re-indented one level inside $indent.
     */
    private function arm(NodeMatch $match, Expr $arm, string $indent): string
    {
        return new Span(
            $match->file->path,
            $match->file->source,
            $arm->getStartFilePos(),
            $arm->getEndFilePos() + 1,
        )->reindent($indent . '    ');
    }
}
