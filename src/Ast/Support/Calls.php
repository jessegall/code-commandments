<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Ast\Support;

use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;

/**
 * Call-node helpers shared across the query engine.
 */
final class Calls
{
    /**
     * The called method/function/static name, or null when the node is not a call.
     */
    public static function name(Node $node): ?string
    {
        if (($node instanceof MethodCall || $node instanceof NullsafeMethodCall || $node instanceof StaticCall)
            && $node->name instanceof Identifier) {
            return $node->name->toString();
        }

        if ($node instanceof FuncCall && $node->name instanceof Name) {
            return $node->name->toString();
        }

        return null;
    }

    public static function isCallTo(Node $node, string ...$names): bool
    {
        $name = self::name($node);

        return $name !== null && ($names === [] || in_array($name, $names, true));
    }
}
