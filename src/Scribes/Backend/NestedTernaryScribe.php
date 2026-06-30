<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Scribes\Backend;

use JesseGall\CodeCommandments\Ast\NodeMatch;
use JesseGall\CodeCommandments\Scribes\RepentScribe;
use PhpParser\Node;
use PhpParser\Node\Expr\Ternary;
use PhpParser\NodeFinder;

/**
 * Fixes {@see \JesseGall\CodeCommandments\Detectors\Backend\NestedTernaryDetector}: an
 * else-chained ternary (`a ? b : (c ? d : e)`) hides its branching. Unfold it into a flat
 * `match (true) { a => b, c => d, default => e }` — the readable dispatch the skill teaches.
 *
 * Only the clean ELSE-chain is rewritten. A short ternary (`a ?: b`) or a chain nested in a
 * condition/THEN branch can't flatten to a flat match, so it is skipped (the detector still
 * flags it) — correctness over coverage.
 */
final class NestedTernaryScribe extends RepentScribe
{
    public function rewrite(array $findings): array
    {
        return $this->draft($findings)
            ->replace(fn (NodeMatch $match): ?string => $this->toMatch($match))
            ->rewrites();
    }

    private function toMatch(NodeMatch $match): ?string
    {
        $ternary = $match->node;

        if (! $ternary instanceof Ternary) {
            return null;
        }

        $source = $match->file->source;
        $arms = [];
        $node = $ternary;

        // Walk the else-chain, one arm per ternary, until the final (non-ternary) default.
        while ($node instanceof Ternary) {
            // A short ternary, or a ternary buried in the condition/then branch, is not a
            // flat else-chain — bail rather than mangle it.
            if ($node->if === null || $this->containsTernary($node->cond) || $this->containsTernary($node->if)) {
                return null;
            }

            $arms[] = "{$this->slice($source, $node->cond)} => {$this->slice($source, $node->if)}";
            $node = $node->else;
        }

        $arms[] = "default => {$this->slice($source, $node)}";

        $indent = $this->lineIndent($source, $ternary->getStartFilePos());
        $body = implode('', array_map(static fn (string $arm): string => "{$indent}    {$arm},\n", $arms));

        return "match (true) {\n{$body}{$indent}}";
    }

    private function containsTernary(Node $node): bool
    {
        return new NodeFinder()->findFirstInstanceOf($node, Ternary::class) !== null;
    }

    private function slice(string $source, Node $node): string
    {
        return substr($source, $node->getStartFilePos(), $node->getEndFilePos() + 1 - $node->getStartFilePos());
    }

    /**
     * The leading whitespace of the line $offset sits on — the base the match block aligns to.
     */
    private function lineIndent(string $source, int $offset): string
    {
        $lineStart = strrpos(substr($source, 0, $offset), "\n");
        $lineStart = $lineStart === false ? 0 : $lineStart + 1;

        return substr($source, $lineStart, strspn($source, " \t", $lineStart));
    }
}
