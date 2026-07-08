<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Scribes\Backend;

use JesseGall\CodeCommandments\Ast\NodeMatch;
use JesseGall\CodeCommandments\Scribes\RepentScribe;
use PhpParser\Node\Expr\MethodCall;

/**
 * Drops the redundant `->toArray()` from `X::from(...)->toArray()` in a slot that re-hydrates it, leaving
 * the `X::from(...)` — the slot takes the object directly.
 */
final class DataToArrayRoundtripScribe extends RepentScribe
{
    public function rewrite(array $findings): array
    {
        return $this->draft($findings)
            ->replace(fn (NodeMatch $match): ?string => $this->dropToArray($match))
            ->rewrites();
    }

    private function dropToArray(NodeMatch $match): ?string
    {
        $node = $match->node;

        if (! $node instanceof MethodCall) {
            return null;
        }

        $receiver = $node->var;
        $source = $match->file->source;

        return substr($source, $receiver->getStartFilePos(), $receiver->getEndFilePos() + 1 - $receiver->getStartFilePos());
    }
}
