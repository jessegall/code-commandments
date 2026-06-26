<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Ast;

use JesseGall\CodeCommandments\Ast\Support\Calls;
use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\ArrayItem;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrayDimFetch;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\BinaryOp\Coalesce;
use PhpParser\Node\Expr\BinaryOp\Identical;
use PhpParser\Node\Expr\BinaryOp\NotIdentical;
use PhpParser\Node\Expr\Cast;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Match_;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Expr\NullsafePropertyFetch;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\Throw_;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\FunctionLike;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\NodeFinder;
use PhpParser\Node\NullableType;
use PhpParser\Node\Scalar\Float_;
use PhpParser\Node\Scalar\Int_;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Catch_;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Enum_;
use PhpParser\Node\Stmt\Function_;
use PhpParser\Node\Stmt\Property;
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
     * The left-hand side of a `??`, or an empty node when this is not a coalesce.
     */
    public function coalesceLeft(): self
    {
        return new self($this->node instanceof Coalesce ? $this->node->left : null);
    }

    /**
     * Is this the `null` literal?
     */
    public function isNull(): bool
    {
        return $this->node instanceof ConstFetch && $this->node->name->toLowerString() === 'null';
    }

    /**
     * Is this an empty/zero "fake" literal — `''`, `[]`, `0`, `0.0`, `false`?
     * The kind of value manufactured to fill a slot when the real one is absent.
     */
    public function isEmptyLiteral(): bool
    {
        return match (true) {
            $this->node instanceof String_ => $this->node->value === '',
            $this->node instanceof Array_ => $this->node->items === [],
            $this->node instanceof Int_ => $this->node->value === 0,
            $this->node instanceof Float_ => $this->node->value === 0.0,
            $this->node instanceof ConstFetch => $this->node->name->toLowerString() === 'false',
            default => false,
        };
    }

    /**
     * Is this expression's result immediately de-nulled by the caller — consumed
     * with `?->`, `?? …`, or compared `=== null` / `!== null`? The tell that a
     * `?T` return is being null-checked at the call site instead of at the source.
     */
    public function isDeNulled(): bool
    {
        $parent = $this->parent()->node;

        if (($parent instanceof NullsafeMethodCall || $parent instanceof NullsafePropertyFetch) && $parent->var === $this->node) {
            return true;
        }

        if ($parent instanceof Coalesce && $parent->left === $this->node) {
            return true;
        }

        if ($parent instanceof Identical || $parent instanceof NotIdentical) {
            $other = $parent->left === $this->node ? $parent->right : $parent->left;

            return ($parent->left === $this->node || $parent->right === $this->node)
                && $other instanceof ConstFetch
                && $other->name->toLowerString() === 'null';
        }

        return false;
    }

    /**
     * Classify what happens to a variable AT THIS occurrence — written, passed,
     * called on, null-checked, returned… The per-stop verdict behind {@see trace}.
     */
    public function interactionKind(): InteractionKind
    {
        $parent = $this->parent()->node;

        return match (true) {
            $parent instanceof Assign && $parent->var === $this->node => InteractionKind::Assigned,
            ($parent instanceof Identical || $parent instanceof NotIdentical) && $this->isDeNulled() => InteractionKind::NullChecked,
            $parent instanceof Coalesce && $parent->left === $this->node => InteractionKind::Coalesced,
            ($parent instanceof NullsafeMethodCall || $parent instanceof NullsafePropertyFetch) && $parent->var === $this->node => InteractionKind::Nullsafe,
            $this->isReturnedValue() => InteractionKind::Returned,
            $this->isCallReceiver() => InteractionKind::MethodCall,
            $parent instanceof PropertyFetch && $parent->var === $this->node => InteractionKind::PropertyFetch,
            $this->isCallArgument() => InteractionKind::Argument,
            default => InteractionKind::Read,
        };
    }

    /**
     * Is this expression's result de-nulled — directly ({@see isDeNulled}) OR via
     * the variable it's assigned to (`$x = finder(); if ($x === null) …` / `$x?->`)
     * somewhere in the same function? The everyday form of "every caller checks it".
     */
    public function resultIsDeNulled(): bool
    {
        if ($this->isDeNulled()) {
            return true;
        }

        $parent = $this->parent()->node;

        if (! $parent instanceof Assign || ! $parent->var instanceof Variable || ! is_string($parent->var->name)) {
            return false;
        }

        $function = $this->enclosingFunction();

        if ($function === null) {
            return false;
        }

        foreach ((new NodeFinder)->findInstanceOf([$function], Variable::class) as $use) {
            if ($use !== $parent->var && $use->name === $parent->var->name && new self($use)->isDeNulled()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Does this expression fill a call/constructor argument (seen through any
     * surrounding casts, e.g. `foo(name: (int) ($x ?? 0))`)?
     */
    public function fillsArgument(): bool
    {
        $current = $this->parent();

        while ($current->node instanceof Cast) {
            $current = $current->parent();
        }

        return $current->node instanceof Arg;
    }

    /**
     * Is this `$this->prop[$key]` — a lookup into a keyed store the class owns?
     */
    public function isOwnedKeyedLookup(): bool
    {
        return $this->node instanceof ArrayDimFetch
            && $this->node->var instanceof PropertyFetch
            && $this->node->var->var instanceof Variable
            && $this->node->var->var->name === 'this';
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
     * The resolved class name of a `Class::method(...)` static call, or null.
     * Names are resolved at parse time, so this is fully qualified.
     */
    public function staticCallClass(): ?string
    {
        return $this->node instanceof StaticCall && $this->node->class instanceof Name
            ? $this->node->class->toString()
            : null;
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
     * Is this a function/method declared to return a nullable array (`?array` /
     * `array | null`) — a collection modelled as "the list, or null"?
     */
    public function returnsNullableArray(): bool
    {
        return ($this->node instanceof ClassMethod || $this->node instanceof Function_)
            && TypeName::isNullableArray($this->node->returnType);
    }

    /**
     * Does this declaration (param, property, or return) type something as a
     * nullable `Option` — `?Option` / `Option | null` — an Option wearing a null
     * costume?
     */
    public function declaresNullableOption(): bool
    {
        $type = match (true) {
            $this->node instanceof Param => $this->node->type,
            $this->node instanceof Property => $this->node->type,
            $this->node instanceof ClassMethod, $this->node instanceof Function_ => $this->node->returnType,
            default => null,
        };

        $class = TypeName::nullableClass($type);

        return $class !== null && self::shortName($class) === 'Option';
    }

    /**
     * Is this `->unwrapOr(null)` — collapsing an Option straight back to a nullable?
     */
    public function isUnwrapOrNull(): bool
    {
        if (! $this->node instanceof MethodCall && ! $this->node instanceof NullsafeMethodCall) {
            return false;
        }

        if (! $this->node->name instanceof Identifier || $this->node->name->toString() !== 'unwrapOr') {
            return false;
        }

        $args = $this->arguments();

        return isset($args[0]) && new self($args[0]->value)->isNull();
    }

    /**
     * Is this a `catch` that swallows the failure into absence — an empty body,
     * or whose only effect is `return null/false/[]` (or `return;`)? A catch that
     * logs, rethrows, or does real recovery is not a swallow.
     */
    public function isSwallowedCatch(): bool
    {
        if (! $this->node instanceof Catch_) {
            return false;
        }

        if ($this->node->stmts === []) {
            return true;
        }

        if (count($this->node->stmts) !== 1 || ! $this->node->stmts[0] instanceof Return_) {
            return false;
        }

        return new self($this->node->stmts[0]->expr)->isAbsenceValue();
    }

    /**
     * Is this an absence value — `null`, `false`, an empty array, or nothing?
     */
    public function isAbsenceValue(): bool
    {
        return $this->node === null
            || $this->isNull()
            || ($this->node instanceof ConstFetch && $this->node->name->toLowerString() === 'false')
            || ($this->node instanceof Array_ && $this->node->items === []);
    }

    private static function shortName(string $fqcn): string
    {
        $parts = explode('\\', $fqcn);

        return end($parts);
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
