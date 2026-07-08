<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Scribes\Backend;

use JesseGall\CodeCommandments\Ast\NodeMatch;
use JesseGall\CodeCommandments\Scribes\RepentScribe;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\StaticCall;

/**
 * Unwraps a redundant `X::from([...])` to its plain array literal, letting the parent `::from` auto-hydrate
 * it. Rewrites only the array-literal form (the detector's shape); anything else is left untouched.
 */
final class RedundantNestedFromScribe extends RepentScribe
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

        if (! $node instanceof StaticCall) {
            return null;
        }

        $args = array_values(array_filter($node->args, static fn ($arg): bool => $arg instanceof Arg));
        $array = count($args) === 1 ? $args[0]->value : null;

        if (! $array instanceof Array_) {
            return null;
        }

        $source = $match->file->source;

        return substr($source, $array->getStartFilePos(), $array->getEndFilePos() + 1 - $array->getStartFilePos());
    }
}
