<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Ast\Support;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\BinaryOp\Coalesce;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Expr\Throw_;
use PhpParser\Node\Name;

/**
 * Small, named predicates over raw AST nodes, so a detector's pattern reads as
 * intent rather than a chain of `instanceof`.
 */
final class Nodes
{
    /**
     * The node's parent (linked during parsing), or null at the root.
     */
    public static function parentOf(Node $node): ?Node
    {
        $parent = $node->getAttribute('parent');

        return $parent instanceof Node ? $parent : null;
    }

    /**
     * Is this a `throw` expression?
     */
    public static function isThrow(?Node $node): bool
    {
        return $node instanceof Throw_;
    }

    /**
     * The right-hand side of a `??`, or null when the node is not a coalesce.
     */
    public static function coalesceRight(Node $node): ?Node
    {
        return $node instanceof Coalesce ? $node->right : null;
    }

    /**
     * Does the node sit in a call's argument list?
     */
    public static function isCallArgument(Node $node): bool
    {
        return self::parentOf($node) instanceof Arg;
    }

    /**
     * Is the node the receiver a method is called on (`$node->method()`)?
     */
    public static function isCallReceiver(Node $node): bool
    {
        $parent = self::parentOf($node);

        return ($parent instanceof MethodCall || $parent instanceof NullsafeMethodCall) && $parent->var === $node;
    }

    /**
     * The class name of a `new X(...)`, or null when the node is not a `new`.
     */
    public static function newClassName(Node $node): ?string
    {
        return $node instanceof New_ && $node->class instanceof Name ? $node->class->toString() : null;
    }
}
