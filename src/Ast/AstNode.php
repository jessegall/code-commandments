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
use PhpParser\Node\Expr\BinaryOp\BooleanOr;
use PhpParser\Node\Expr\BinaryOp\Coalesce;
use PhpParser\Node\Expr\BinaryOp\Identical;
use PhpParser\Node\Expr\BinaryOp\NotIdentical;
use PhpParser\Node\Expr\Cast;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Match_;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Expr\NullsafePropertyFetch;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\Ternary;
use PhpParser\Node\Expr\Throw_;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\FunctionLike;
use PhpParser\Node\Identifier;
use PhpParser\Node\IntersectionType;
use PhpParser\Node\Name;
use PhpParser\NodeFinder;
use PhpParser\Node\NullableType;
use PhpParser\Node\UnionType;
use PhpParser\Node\Param;
use PhpParser\Node\Scalar\Float_;
use PhpParser\Node\Scalar\Int_;
use PhpParser\Node\Scalar\InterpolatedString;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Break_;
use PhpParser\Node\Stmt\Catch_;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassConst;
use PhpParser\Node\Stmt\Continue_;
use PhpParser\Node\Stmt\Expression;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Do_;
use PhpParser\Node\Stmt\Enum_;
use PhpParser\Node\Stmt\For_;
use PhpParser\Node\Stmt\Foreach_;
use PhpParser\Node\Stmt\Function_;
use PhpParser\Node\Stmt\If_;
use PhpParser\Node\Stmt\Property;
use PhpParser\Node\Stmt\Return_;
use PhpParser\Node\Stmt\Switch_;
use PhpParser\Node\Stmt\While_;

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
            $parent instanceof PropertyFetch && $parent->var === $this->node => new self($parent)->isAssignmentTarget()
                ? InteractionKind::PropertyWrite
                : InteractionKind::PropertyFetch,
            $this->isCallArgument() => InteractionKind::Argument,
            default => InteractionKind::Read,
        };
    }

    /**
     * Is this node a parameter's default value (`function f($x = <here>)`)? A
     * `new` in default position is the one place the spatie-data skill permits it.
     */
    public function isParameterDefault(): bool
    {
        $parent = $this->parent()->node;

        return $parent instanceof Param && $parent->default === $this->node;
    }

    /**
     * Is this a concrete class declaration that is NOT `final`?
     */
    public function isNonFinalClass(): bool
    {
        return $this->node instanceof Class_ && ! $this->node->isFinal() && ! $this->node->isAbstract();
    }

    /**
     * Is this node the left-hand side of an assignment (`$this = …`)?
     */
    public function isAssignmentTarget(): bool
    {
        $parent = $this->parent()->node;

        return $parent instanceof Assign && $parent->var === $this->node;
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
     * For a class declaration: does its constructor promote at least one property
     * and is EVERY promoted property optional (nullable or defaulted)? An all-
     * optional record whose type tells no truth about what's actually required.
     */
    public function everyConstructorParamOptional(): bool
    {
        if (! $this->node instanceof Class_) {
            return false;
        }

        $constructor = $this->node->getMethod('__construct');

        if ($constructor === null) {
            return false;
        }

        $promoted = array_filter($constructor->params, static fn (Param $param): bool => $param->flags !== 0);

        if ($promoted === []) {
            return false;
        }

        foreach ($promoted as $param) {
            if ($param->default === null && ! TypeName::isNullable($param->type)) {
                return false;
            }
        }

        return true;
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
     * Is this a function/method declared to return a nullable CLASS (`?C` /
     * `C | null`) — a finder that resolves to a value-or-null?
     */
    public function returnsNullableObject(): bool
    {
        return ($this->node instanceof ClassMethod || $this->node instanceof Function_)
            && TypeName::nullableClass($this->node->returnType) !== null;
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
     * Is this a `match` whose `default` arm returns an absence value
     * (`null`/`false`/`[]`) instead of throwing? An unhandled case silently
     * swallowed — a missing case is a bug, and the default should say so.
     */
    public function isMatchWithAbsenceDefault(): bool
    {
        if (! $this->node instanceof Match_) {
            return false;
        }

        foreach ($this->node->arms as $arm) {
            if ($arm->conds === null) {
                return new self($arm->body)->isAbsenceValue();
            }
        }

        return false;
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

    /**
     * Is this `$x->update([...])` on something other than `$this` — a bare mass
     * array-update on another object (a model at a call site, not the model's own
     * intention method)?
     */
    public function isMassArrayUpdate(): bool
    {
        if (! $this->node instanceof MethodCall || ! $this->node->name instanceof Identifier || $this->node->name->toString() !== 'update') {
            return false;
        }

        if ($this->node->var instanceof Variable && $this->node->var->name === 'this') {
            return false;
        }

        $args = $this->arguments();

        return isset($args[0]) && $args[0]->value instanceof Array_;
    }

    /**
     * Is this a `throw new X(...)` inside a `catch` that does NOT pass the caught
     * exception on as its cause — the original stack trace dropped on the floor?
     */
    public function isRethrowWithoutCause(): bool
    {
        if (! $this->node instanceof New_ || ! $this->parent()->isThrow()) {
            return false;
        }

        $catch = $this->walkUp(static fn (Node $node): bool => $node instanceof Catch_);

        if (! $catch instanceof Catch_ || ! $catch->var instanceof Variable || ! is_string($catch->var->name)) {
            return false;
        }

        foreach ((new NodeFinder)->findInstanceOf($this->node->args, Variable::class) as $variable) {
            if ($variable->name === $catch->var->name) {
                return false; // the caught exception is passed on
            }
        }

        return true;
    }

    /**
     * Is this a `throw new X("…message…")` — an exception built with a literal
     * (or interpolated) message string at the throw site, rather than a named
     * factory carrying domain values?
     */
    public function isThrownWithMessage(): bool
    {
        if (! $this->node instanceof New_ || ! $this->parent()->isThrow()) {
            return false;
        }

        $args = $this->arguments();

        return isset($args[0]) && ($args[0]->value instanceof String_ || $args[0]->value instanceof InterpolatedString);
    }

    /**
     * Does this class-like declaration carry a multi-PARAGRAPH docblock — two or
     * more blank-line-separated runs of prose? An essay on a class is the class
     * asking to be split.
     */
    public function hasMultiParagraphDocblock(): bool
    {
        if (! $this->node instanceof ClassLike) {
            return false;
        }

        $doc = $this->node->getDocComment();

        if ($doc === null) {
            return false;
        }

        $paragraphs = 0;
        $inParagraph = false;

        foreach (preg_split('/\R/', $doc->getText()) ?: [] as $line) {
            $line = trim(ltrim(trim($line), '/*'));
            $isProse = $line !== '' && ! str_starts_with($line, '@');

            if ($isProse && ! $inParagraph) {
                $paragraphs++;
                $inParagraph = true;
            } elseif ($line === '') {
                $inParagraph = false;
            }
        }

        return $paragraphs >= 2;
    }

    /**
     * Does this function/method carry a "ceremony" docblock — one with NO prose
     * summary whose every tag merely restates the typed signature (`@param Type
     * $x` with no description, on an already-typed param; an optional bare
     * `@return Type`)? Such a block is pure noise the signature already says. A
     * description, a generic/shape refinement (`<`, `{`, `|`), or any other tag
     * (`@throws`, `@deprecated`, …) means it earns its keep and is left alone.
     */
    public function hasCeremonyDocblock(): bool
    {
        if (! $this->node instanceof ClassMethod && ! $this->node instanceof Function_) {
            return false;
        }

        $doc = $this->node->getDocComment();

        if ($doc === null) {
            return false;
        }

        $nativeTypes = [];

        foreach ($this->node->params as $param) {
            $type = self::typeToString($param->type);

            if ($type !== null && $param->var instanceof Variable && is_string($param->var->name)) {
                $nativeTypes[$param->var->name] = $type;
            }
        }

        $nativeReturn = self::typeToString($this->node->getReturnType());

        $restatements = 0;

        foreach (preg_split('/\R/', $doc->getText()) ?: [] as $line) {
            $line = trim(ltrim(trim($line), '/*'));

            if ($line === '') {
                continue;
            }

            if (! str_starts_with($line, '@')) {
                return false; // a prose summary — real documentation.
            }

            if (preg_match('/^@param\s+(\S+)\s+\$(\w+)\s*(.*)$/', $line, $m) === 1) {
                $name = $m[2];

                // Only a pure restatement: a description or a doc-type that differs
                // at all from the native type (a refinement like `T`, `Foo[]`,
                // `array<…>`) means the tag adds information and earns its keep.
                if (trim($m[3]) !== '' || ! isset($nativeTypes[$name]) || self::typeKey($m[1]) !== $nativeTypes[$name]) {
                    return false;
                }

                $restatements++;

                continue;
            }

            if (preg_match('/^@return\s+(\S+)\s*(.*)$/', $line, $m) === 1) {
                if (trim($m[2]) !== '' || $nativeReturn === null || self::typeKey($m[1]) !== $nativeReturn) {
                    return false;
                }

                continue;
            }

            return false; // any other tag (@throws, @deprecated, @see, …) earns its keep.
        }

        return $restatements >= 1;
    }

    /**
     * Is this a "const class" of scalars — a class whose entire body is scalar
     * class constants (no methods, no properties)? A closed set of values hand-
     * rolled as constants instead of a native backed enum.
     */
    public function isScalarConstClass(): bool
    {
        if (! $this->node instanceof Class_) {
            return false;
        }

        $constants = 0;

        foreach ($this->node->stmts as $stmt) {
            if ($stmt instanceof ClassConst) {
                foreach ($stmt->consts as $const) {
                    if (! new self($const->value)->isScalarLiteral()) {
                        return false;
                    }

                    $constants++;
                }

                continue;
            }

            return false; // a method, property, or anything else — not a pure const class
        }

        return $constants >= 2;
    }

    /**
     * Is this a scalar literal — a string, int, or float?
     */
    public function isScalarLiteral(): bool
    {
        return $this->node instanceof String_
            || $this->node instanceof Int_
            || $this->node instanceof Float_;
    }

    /**
     * Is this the OUTERMOST node of a nested ternary — a `?:` with another `?:` in
     * its branches and no enclosing ternary of its own? Chained ternaries hide
     * control flow in one unreadable expression; reach for `match`/guard clauses.
     */
    public function isOutermostNestedTernary(): bool
    {
        if (! $this->node instanceof Ternary) {
            return false;
        }

        // Only the root of the chain is flagged, so one tree yields one finding.
        $parent = $this->node->getAttribute('parent');

        while ($parent instanceof Node && ! $parent instanceof FunctionLike) {
            if ($parent instanceof Ternary) {
                return false;
            }

            $parent = $parent->getAttribute('parent');
        }

        foreach ([$this->node->if, $this->node->else] as $branch) {
            if ($branch instanceof Node && (new NodeFinder)->findInstanceOf($branch, Ternary::class) !== []) {
                return true;
            }
        }

        return false;
    }

    /**
     * Is this an `if`/`else` whose `if` branch already exits (ends in
     * `return`/`throw`/`continue`/`break`), making the `else` redundant? Drop the
     * `else` and let the happy path continue unindented.
     */
    public function hasRedundantElse(): bool
    {
        if (! $this->node instanceof If_ || $this->node->else === null || $this->node->elseifs !== []) {
            return false;
        }

        $statements = $this->node->stmts;

        if ($statements === []) {
            return false;
        }

        $last = end($statements);

        return $last instanceof Return_
            || $last instanceof Continue_
            || $last instanceof Break_
            || ($last instanceof Expression && $last->expr instanceof Throw_);
    }

    /**
     * Is this an `if` nested three-deep — two or more enclosing `if`s within the
     * same function? A pyramid of conditions begging for guard clauses / extraction.
     */
    public function isDeeplyNestedIf(): bool
    {
        if (! $this->node instanceof If_) {
            return false;
        }

        $depth = 0;
        $node = $this->node->getAttribute('parent');

        while ($node instanceof Node && ! $node instanceof FunctionLike) {
            if ($node instanceof If_) {
                $depth++;
            }

            $node = $node->getAttribute('parent');
        }

        return $depth >= 2;
    }

    /**
     * Is this an `if` / `elseif` ladder of four-plus branches (an `if` with two or
     * more `elseif`s)? A long ladder is dispatch in disguise — a `match`, a method
     * on the type, or polymorphism.
     */
    public function isIfElseLadder(): bool
    {
        return $this->node instanceof If_ && count($this->node->elseifs) >= 2;
    }

    /**
     * Is this an `if` that is the SOLE statement of its enclosing loop — the whole
     * body wrapped in a condition instead of an inverted `continue` guard?
     */
    public function isSoleLoopBodyGuard(): bool
    {
        if (! $this->node instanceof If_ || $this->node->else !== null || $this->node->elseifs !== []) {
            return false;
        }

        $loop = $this->parent()->node;

        $body = match (true) {
            $loop instanceof Foreach_, $loop instanceof For_, $loop instanceof While_ => $loop->stmts,
            default => null,
        };

        // The whole loop body is this one `if` — and it buries real WORK (≥2
        // statements), not a one-line filter-collect or a search `return`.
        return $body !== null
            && count($body) === 1
            && $body[0] === $this->node
            && count($this->node->stmts) >= 2;
    }

    /**
     * Is this node inside a loop (`for` / `foreach` / `while` / `do-while`)?
     */
    public function isWithinLoop(): bool
    {
        return $this->walkUp(static fn (Node $node): bool =>
            $node instanceof Foreach_
            || $node instanceof For_
            || $node instanceof While_
            || $node instanceof Do_) !== null;
    }

    private static function shortName(string $fqcn): string
    {
        $parts = explode('\\', $fqcn);

        return end($parts);
    }

    /**
     * The string/int literals used as `match`/`switch` arm conditions — e.g.
     * `'pending'`, `'paid'` in `match ($x) { 'pending' => …, 'paid' => … }`.
     *
     * @return list<string>
     */
    public function armConditionLiterals(): array
    {
        $literals = [];

        if ($this->node instanceof Match_) {
            foreach ($this->node->arms as $arm) {
                foreach ($arm->conds ?? [] as $cond) {
                    $literal = self::scalarLiteral($cond);

                    if ($literal !== null) {
                        $literals[] = $literal;
                    }
                }
            }
        } elseif ($this->node instanceof Switch_) {
            foreach ($this->node->cases as $case) {
                $literal = $case->cond === null ? null : self::scalarLiteral($case->cond);

                if ($literal !== null) {
                    $literals[] = $literal;
                }
            }
        }

        return $literals;
    }

    /**
     * The scalar values of the array literal passed as argument $index — e.g.
     * `['a', 'b']` in `in_array($x, ['a', 'b'])`.
     *
     * @return list<string>
     */
    public function argumentArrayLiterals(int $index): array
    {
        $args = $this->arguments();

        if (! isset($args[$index]) || ! $args[$index]->value instanceof Array_) {
            return [];
        }

        $literals = [];

        foreach ($args[$index]->value->items as $item) {
            if ($item instanceof ArrayItem) {
                $literal = self::scalarLiteral($item->value);

                if ($literal !== null) {
                    $literals[] = $literal;
                }
            }
        }

        return $literals;
    }

    /**
     * If this node is the OUTERMOST `||` chain, the class FQCN that two-or-more of
     * its `===`/`!==` operands compare against (`$x === Foo::A || $x === Foo::B`).
     * Returns null when the chain isn't a same-class constant membership test. The
     * caller decides whether that class is a backed enum worth a group method.
     */
    public function orChainComparedClass(): ?string
    {
        if (! $this->node instanceof BooleanOr) {
            return null;
        }

        if ($this->node->getAttribute('parent') instanceof BooleanOr) {
            return null;
        }

        $counts = [];

        foreach ($this->flattenOr($this->node) as $operand) {
            $class = self::comparedConstClass($operand);

            if ($class !== null) {
                $counts[$class] = ($counts[$class] ?? 0) + 1;
            }
        }

        foreach ($counts as $class => $count) {
            if ($count >= 2) {
                return $class;
            }
        }

        return null;
    }

    /**
     * @return list<Node>
     */
    private function flattenOr(BooleanOr $node): array
    {
        $operands = [];

        foreach ([$node->left, $node->right] as $side) {
            if ($side instanceof BooleanOr) {
                $operands = array_merge($operands, $this->flattenOr($side));
            } else {
                $operands[] = $side;
            }
        }

        return $operands;
    }

    private static function comparedConstClass(Node $operand): ?string
    {
        if (! $operand instanceof Identical && ! $operand instanceof NotIdentical) {
            return null;
        }

        foreach ([$operand->left, $operand->right] as $side) {
            if ($side instanceof ClassConstFetch && $side->class instanceof Name) {
                return $side->class->toString();
            }
        }

        return null;
    }

    /**
     * The signature of this function/method's VALUE parameters — its
     * scalar-typed params (`string`/`int`/`float`/`bool`) rendered as a sorted
     * `"type $name"` list, but only when there are three or more (a data clump).
     * Returns an empty list otherwise. A clump recurring across signatures is the
     * tell that these fields are really one object.
     *
     * @return list<string>
     */
    public function valueParamSignature(): array
    {
        if (! $this->node instanceof ClassMethod && ! $this->node instanceof Function_) {
            return [];
        }

        $fields = [];

        foreach ($this->node->params as $param) {
            if (! $param->type instanceof Identifier || ! $param->var instanceof Variable || ! is_string($param->var->name)) {
                continue;
            }

            $type = strtolower($param->type->toString());

            if (in_array($type, ['string', 'int', 'float', 'bool'], true)) {
                $fields[] = $type . ' $' . $param->var->name;
            }
        }

        if (count($fields) < 3) {
            return [];
        }

        sort($fields);

        return $fields;
    }

    /**
     * Render a native type declaration to a normalised key (lowercased, leading
     * `?` and `\` stripped, union members sorted) so it can be compared against a
     * docblock type. Returns null when there is no native type.
     */
    private static function typeToString(?Node $type): ?string
    {
        if ($type === null) {
            return null;
        }

        if ($type instanceof NullableType) {
            $inner = self::typeToString($type->type);

            return $inner === null ? null : self::typeKey('?' . $inner);
        }

        if ($type instanceof UnionType || $type instanceof IntersectionType) {
            $glue = $type instanceof UnionType ? '|' : '&';
            $parts = array_map(static fn (Node $part): string => (string) self::typeToString($part), $type->types);

            return self::typeKey(implode($glue, $parts));
        }

        if ($type instanceof Name || $type instanceof Identifier) {
            return self::typeKey($type->toString());
        }

        return null;
    }

    private static function typeKey(string $type): string
    {
        $type = strtolower(ltrim($type, '?\\'));
        $type = str_replace('null|', '', str_replace('|null', '', $type));

        if (str_contains($type, '|')) {
            $parts = array_filter(array_map(static fn (string $p): string => ltrim($p, '\\'), explode('|', $type)));
            sort($parts);
            $type = implode('|', $parts);
        }

        return $type;
    }

    private static function scalarLiteral(Node $expr): ?string
    {
        return match (true) {
            $expr instanceof String_ => $expr->value,
            $expr instanceof Int_ => (string) $expr->value,
            default => null,
        };
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
     * Does this array literal have at least one value that is itself an array — a
     * NESTED structure (a config / schema / payload tree) rather than a flat
     * record of fields? Flat records are value-object candidates; trees are not.
     */
    public function hasNestedArrayValue(): bool
    {
        if (! $this->node instanceof Array_) {
            return false;
        }

        foreach ($this->node->items as $item) {
            if ($item instanceof ArrayItem && $item->value instanceof Array_) {
                return true;
            }
        }

        return false;
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
