<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Scribes\Backend;

use JesseGall\CodeCommandments\Ast\NodeMatch;
use JesseGall\CodeCommandments\Ast\Support\Negation;
use JesseGall\CodeCommandments\Span;
use JesseGall\CodeCommandments\Scribes\Writer;
use PhpParser\Node;
use PhpParser\Node\Expr\BinaryOp;
use PhpParser\Node\Expr\BinaryOp\BooleanAnd;
use PhpParser\Node\Expr\BinaryOp\LogicalAnd;
use PhpParser\Node\Stmt\Expression;

/**
 * Fixes {@see \JesseGall\CodeCommandments\Detectors\Backend\ShortCircuitStatementDetector}: a bare
 * `$a && $b->do();` is a branch wearing an expression's clothes, so it is spelled OUT — the left side
 * becomes the `if` condition and the right side its one statement. An `||`/`or` says "unless", so its
 * condition is flipped by {@see Negation} (a `!` in front, parenthesised only where that changes
 * meaning), and a CHAIN keeps its whole left side verbatim: `$a && $b && work()` tests `$a && $b`.
 * The whole STATEMENT is replaced (see {@see target}), the block opens in the file's own brace style
 * ({@see NodeMatch::controlBlockOpener} — read from a control structure the file already has, since
 * the statement being replaced holds no brace to learn from), and the work is re-indented one level
 * in: nothing is left for a human to reformat (#416).
 */
final class ShortCircuitStatementScribe extends SingleReplacementScribe
{
    protected function replacement(NodeMatch $match): ?string
    {
        $operator = $match->node;

        if (! $operator instanceof BinaryOp || ! $match->parent()->node instanceof Expression) {
            return null;
        }

        $source = $match->file->source;
        $indent = $match->span()->lineIndent();

        $condition = $this->conjunction($operator)
            ? Writer::slice($source, $operator->left)
            : Negation::of($operator->left, $source);

        $opener = $match->controlBlockOpener($indent);
        $work = new Span($match->file->path, $source, $operator->right->getStartFilePos(), $operator->right->getEndFilePos() + 1)
            ->reindent($indent . '    ');

        return "if ({$condition}){$opener}\n{$work};\n{$indent}}";
    }

    protected function target(NodeMatch $match): Node
    {
        return $match->parent()->node;
    }

    /**
     * Does the operator run its right side when the left HOLDS — an `&&`/`and`, whose condition
     * therefore transfers to the `if` as written? An `||`/`or` runs it when the left does not.
     */
    private function conjunction(BinaryOp $operator): bool
    {
        return $operator instanceof BooleanAnd || $operator instanceof LogicalAnd;
    }
}
