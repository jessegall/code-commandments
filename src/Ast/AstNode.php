<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Ast;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\BinaryOp\Coalesce;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Expr\Throw_;
use PhpParser\Node\Name;

/**
 * A raw AST node wrapped with fluent, language-level predicates, so a detector's
 * pattern reads as intent (`$node->coalesceRight()?->isThrow()`) rather than a
 * chain of `instanceof`. Handed to the `Codebase::where()` selector.
 */
final class AstNode
{
    public function __construct(public readonly Node $node) {}

    /**
     * The parent node (linked during parsing), or null at the root.
     */
    public function parent(): ?self
    {
        $parent = $this->node->getAttribute('parent');

        return $parent instanceof Node ? new self($parent) : null;
    }

    /**
     * Is this a `throw` expression?
     */
    public function isThrow(): bool
    {
        return $this->node instanceof Throw_;
    }

    /**
     * Is this a `??` coalesce?
     */
    public function isCoalesce(): bool
    {
        return $this->node instanceof Coalesce;
    }

    /**
     * The right-hand side of a `??`, or null when this is not a coalesce.
     */
    public function coalesceRight(): ?self
    {
        return $this->node instanceof Coalesce ? new self($this->node->right) : null;
    }

    /**
     * Does this node sit in a call's argument list?
     */
    public function isCallArgument(): bool
    {
        return $this->parent()?->node instanceof Arg;
    }

    /**
     * Is this node the receiver a method is called on (`$node->method()`)?
     */
    public function isCallReceiver(): bool
    {
        $parent = $this->parent()?->node;

        return ($parent instanceof MethodCall || $parent instanceof NullsafeMethodCall) && $parent->var === $this->node;
    }

    /**
     * The class name of a `new X(...)`, or null when this is not a `new`.
     */
    public function newClassName(): ?string
    {
        return $this->node instanceof New_ && $this->node->class instanceof Name ? $this->node->class->toString() : null;
    }
}
