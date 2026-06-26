<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Ast;

use JesseGall\CodeCommandments\Ast\Support\Calls;
use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\BinaryOp\Coalesce;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Expr\Throw_;
use PhpParser\Node\FunctionLike;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Function_;

/**
 * A node wrapped with fluent, language-level predicates. Navigation never
 * returns null — an absent node yields an empty AstNode whose predicates are all
 * false — so a pattern reads as `$node->coalesceRight()->isThrow()` with no
 * `?->` ceremony. The null-object pattern, applied to the engine itself.
 */
class AstNode
{
    public function __construct(public readonly ?Node $node = null) {}

    /**
     * The parent node, or an empty node at the root.
     */
    public function parent(): self
    {
        $parent = $this->node?->getAttribute('parent');

        return new self($parent instanceof Node ? $parent : null);
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
     * The right-hand side of a `??`, or an empty node when this is not a coalesce.
     */
    public function coalesceRight(): self
    {
        return new self($this->node instanceof Coalesce ? $this->node->right : null);
    }

    /**
     * Does this node sit in a call's argument list?
     */
    public function isCallArgument(): bool
    {
        return $this->parent()->node instanceof Arg;
    }

    /**
     * Is this node the receiver a method is called on (`$node->method()`)?
     */
    public function isCallReceiver(): bool
    {
        $parent = $this->parent()->node;

        return ($parent instanceof MethodCall || $parent instanceof NullsafeMethodCall) && $parent->var === $this->node;
    }

    /**
     * The class name of a `new X(...)`, or null when this is not a `new`.
     */
    public function newClassName(): ?string
    {
        return $this->node instanceof New_ && $this->node->class instanceof Name ? $this->node->class->toString() : null;
    }

    /**
     * The called method/function name when this is a call, else null.
     */
    public function callName(): ?string
    {
        return $this->node === null ? null : Calls::name($this->node);
    }

    /**
     * This node's call / attribute arguments (variadic placeholders dropped).
     *
     * @return list<Arg>
     */
    public function arguments(): array
    {
        if (! isset($this->node->args) || ! is_array($this->node->args)) {
            return [];
        }

        return array_values(array_filter($this->node->args, static fn ($arg): bool => $arg instanceof Arg));
    }

    /**
     * The class/interface/trait/enum this node sits in (or is), or null.
     */
    public function enclosingClass(): ?ClassLike
    {
        if ($this->node instanceof ClassLike) {
            return $this->node;
        }

        return $this->walkUp(static fn (Node $node): bool => $node instanceof ClassLike);
    }

    /**
     * The fully-qualified name of the enclosing class, or null.
     */
    public function enclosingClassName(): ?string
    {
        $class = $this->enclosingClass();

        if ($class === null) {
            return null;
        }

        return ($class->namespacedName ?? null)?->toString() ?? $class->name?->toString();
    }

    /**
     * The function/method/closure this node sits in (or is), or null.
     */
    public function enclosingFunction(): ?FunctionLike
    {
        if ($this->node instanceof FunctionLike) {
            return $this->node;
        }

        return $this->walkUp(static fn (Node $node): bool => $node instanceof FunctionLike);
    }

    /**
     * The enclosing method/function name, or null for a closure or file scope.
     */
    public function enclosingFunctionName(): ?string
    {
        $function = $this->enclosingFunction();

        return ($function instanceof ClassMethod || $function instanceof Function_)
            ? $function->name->toString()
            : null;
    }

    /**
     * The declaration this node sits in: `Class::method`, or `Class`.
     */
    public function scope(): string
    {
        $class = $this->enclosingClassName() ?? '(file)';
        $method = $this->enclosingFunctionName();

        return $method === null ? $class : "{$class}::{$method}";
    }

    private function walkUp(callable $test): ?Node
    {
        $node = $this->node?->getAttribute('parent');

        while ($node instanceof Node) {
            if ($test($node)) {
                return $node;
            }

            $node = $node->getAttribute('parent');
        }

        return null;
    }
}
