<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Ast;

use JesseGall\CodeCommandments\Ast\Support\Calls;
use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\ArrayItem;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrayDimFetch;
use PhpParser\Node\Expr\BinaryOp\Coalesce;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\Match_;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Expr\NullsafePropertyFetch;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\Throw_;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\FunctionLike;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\NullableType;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Enum_;
use PhpParser\Node\Stmt\Function_;
use PhpParser\Node\Stmt\Return_;
use PhpParser\Node\Stmt\Switch_;

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
     * Is this node the value of a `return` statement?
     */
    public function isReturnedValue(): bool
    {
        return $this->parent()->node instanceof Return_;
    }

    /**
     * How many string-literal keys this array literal has (0 if not an array).
     */
    public function stringKeyCount(): int
    {
        if (! $this->node instanceof Array_) {
            return 0;
        }

        $count = 0;

        foreach ($this->node->items as $item) {
            if ($item instanceof ArrayItem && $item->key instanceof String_) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Is this a `match`/`switch` whose subject is `->value` — a backed enum
     * unwrapped to its scalar to be dispatched on (a homeless enum method)?
     * `value` is the language's enum accessor, not a guessed name.
     */
    public function isMatchOnEnumValue(): bool
    {
        $subject = match (true) {
            $this->node instanceof Match_ => $this->node->cond,
            $this->node instanceof Switch_ => $this->node->cond,
            default => null,
        };

        return ($subject instanceof PropertyFetch || $subject instanceof NullsafePropertyFetch)
            && $subject->name instanceof Identifier
            && $subject->name->toString() === 'value';
    }

    /**
     * Is this `$x['literal']` — an array indexed by a string-literal key?
     */
    public function arrayKeyIsString(): bool
    {
        return $this->node instanceof ArrayDimFetch && $this->node->dim instanceof String_;
    }

    /**
     * The name of the variable being indexed (`$bag` in `$bag['x']`), or null.
     */
    public function arrayBaseName(): ?string
    {
        return $this->node instanceof ArrayDimFetch
            && $this->node->var instanceof Variable
            && is_string($this->node->var->name)
            ? $this->node->var->name
            : null;
    }

    /**
     * Is $name a parameter of the enclosing function typed `array`?
     */
    public function enclosingParamIsArray(string $name): bool
    {
        $function = $this->enclosingFunction();

        if ($function === null) {
            return false;
        }

        foreach ($function->getParams() as $param) {
            if ($param->var instanceof Variable && $param->var->name === $name) {
                return self::isArrayType($param->type);
            }
        }

        return false;
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
     * Is this node inside a class (not at file scope)?
     */
    public function isEnclosedInClass(): bool
    {
        return $this->enclosingClass() !== null;
    }

    /**
     * Is the enclosing declaration an enum (which the container can never build)?
     */
    public function isInEnum(): bool
    {
        return $this->enclosingClass() instanceof Enum_;
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

    private static function isArrayType(?Node $type): bool
    {
        if ($type instanceof NullableType) {
            return self::isArrayType($type->type);
        }

        return $type instanceof Identifier && $type->name === 'array';
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
