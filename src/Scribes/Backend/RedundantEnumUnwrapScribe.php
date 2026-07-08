<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Scribes\Backend;

use JesseGall\CodeCommandments\Ast\NodeMatch;
use JesseGall\CodeCommandments\Scribes\RepentScribe;
use PhpParser\Node\Expr\NullsafePropertyFetch;
use PhpParser\Node\Expr\PropertyFetch;

/**
 * Unwraps a redundant enum `->value` at a hydration site (`'status' => $order->status->value`) to its
 * receiver (`$order->status`), letting the property's built-in enum cast keep the enum. The mirror of
 * {@see RedundantNativeCastScribe} (which drops the scalar→enum construction).
 */
final class RedundantEnumUnwrapScribe extends RepentScribe
{
    public function rewrite(array $findings): array
    {
        return $this->draft($findings)
            ->replace(fn (NodeMatch $match): ?string => $this->unwrap($match))
            ->rewrites();
    }

    private function unwrap(NodeMatch $match): ?string
    {
        $node = $match->node;

        if (! $node instanceof PropertyFetch && ! $node instanceof NullsafePropertyFetch) {
            return null;
        }

        $receiver = $node->var;
        $source = $match->file->source;

        return substr($source, $receiver->getStartFilePos(), $receiver->getEndFilePos() + 1 - $receiver->getStartFilePos());
    }
}
