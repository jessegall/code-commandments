<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Scribes\Backend;

use JesseGall\CodeCommandments\Ast\NodeMatch;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\StaticCall;

/**
 * Unwraps a redundant native-cast construction (`Enum::from($x)`, `new DateTime($x)`, `Carbon::parse($x)`)
 * to its single argument, letting the property's built-in enum / date cast build it from the raw scalar.
 */
final class RedundantNativeCastScribe extends SingleReplacementScribe
{
    protected function replacement(NodeMatch $match): ?string
    {
        $node = $match->node;

        if (! $node instanceof StaticCall && ! $node instanceof New_) {
            return null;
        }

        $args = array_values(array_filter($node->args, static fn ($arg): bool => $arg instanceof Arg));

        if (count($args) !== 1) {
            return null;
        }

        $value = $args[0]->value;
        $source = $match->file->source;

        return substr($source, $value->getStartFilePos(), $value->getEndFilePos() + 1 - $value->getStartFilePos());
    }
}
