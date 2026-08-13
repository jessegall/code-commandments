<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Ast;

use JesseGall\PhpTypes\Option;

use JesseGall\CodeCommandments\Support\ClassName;

use JesseGall\CodeCommandments\Ast\Support\Calls;
use JesseGall\CodeCommandments\Ast\Support\ClassLayoutOrder;
use JesseGall\CodeCommandments\Ast\Support\CodeWords;
use JesseGall\CodeCommandments\Ast\Support\Docblock;
use JesseGall\CodeCommandments\Ast\Support\CommentedCode;
use JesseGall\CodeCommandments\Ast\Support\StructuralHash;
use PhpParser\Comment;
use PhpParser\Comment\Doc;
use PhpParser\Modifiers;
use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Attribute;
use PhpParser\Node\ArrayItem;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrayDimFetch;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\AssignOp;
use PhpParser\Node\Expr\AssignOp\Coalesce as CoalesceAssign;
use PhpParser\Node\Expr\AssignOp\Minus as MinusAssign;
use PhpParser\Node\Expr\AssignOp\Plus as PlusAssign;
use PhpParser\Node\Expr\PostDec;
use PhpParser\Node\Expr\PostInc;
use PhpParser\Node\Expr\PreDec;
use PhpParser\Node\Expr\PreInc;
use PhpParser\Node\Expr\BinaryOp\BooleanAnd;
use PhpParser\Node\Expr\BinaryOp\BooleanOr;
use PhpParser\Node\Expr\BinaryOp\Coalesce;
use PhpParser\Node\Expr\BinaryOp\Equal;
use PhpParser\Node\Expr\BinaryOp\Identical;
use PhpParser\Node\Expr\BinaryOp\LogicalAnd;
use PhpParser\Node\Expr\BinaryOp\LogicalOr;
use PhpParser\Node\Expr\BinaryOp\NotEqual;
use PhpParser\Node\Expr\BinaryOp\NotIdentical;
use PhpParser\Node\Expr\BooleanNot;
use PhpParser\Node\Expr\Cast;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\Closure;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\Empty_;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\Instanceof_;
use PhpParser\Node\Expr\Isset_;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\StaticPropertyFetch;
use PhpParser\Node\Expr\Match_;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Expr\NullsafePropertyFetch;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\Ternary;
use PhpParser\Node\Expr\Throw_;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\FunctionLike;
use PhpParser\Node\Identifier;
use PhpParser\Node\MatchArm;
use PhpParser\Node\IntersectionType;
use PhpParser\Node\Name;
use PhpParser\Node\PropertyHook;
use PhpParser\NodeFinder;
use PhpParser\Node\NullableType;
use PhpParser\Node\UnionType;
use PhpParser\Node\UseItem;
use PhpParser\Node\Param;
use PhpParser\Node\Scalar\Float_;
use PhpParser\Node\Scalar;
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
use PhpParser\Node\Stmt\ElseIf_;
use PhpParser\Node\Stmt\Enum_;
use PhpParser\Node\Stmt\For_;
use PhpParser\Node\Stmt\Foreach_;
use PhpParser\Node\Stmt\Function_;
use PhpParser\Node\Stmt\If_;
use PhpParser\Node\Stmt\Interface_;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\Node\Stmt\Property;
use PhpParser\Node\Stmt\Return_;
use PhpParser\Node\Stmt\Switch_;
use PhpParser\Node\Stmt\TryCatch;
use PhpParser\Node\Stmt\While_;

/**
 * A node wrapped with fluent, language-level predicates. Navigation never
 * returns null — an absent node yields an empty AstNode whose predicates are all
 * false — so a pattern reads as `$node->coalesceRight()->isThrow()` with no
 * `?->` ceremony. The null-object pattern, applied to the engine itself.
 */
class AstNode
{
    /**
     * The `is_*` predicates that narrow a value's type — each admits that it may be another type.
     */
    private const array TYPE_PREDICATES = [
        'is_null', 'is_string', 'is_array', 'is_int', 'is_integer', 'is_float', 'is_bool',
        'is_object', 'is_scalar', 'is_iterable', 'is_callable', 'is_numeric', 'is_countable',
    ];

    /**
     * How many fields must ride along untouched before re-threading them is the smell. Below this a
     * two-or-three-field rebuild is just an ordinary constructor call, not a maintenance tax.
     */
    private const int WITHER_CARRIED_FLOOR = 3;

    /**
     * Attributes that OVERRIDE a serialized field's wire type with an explicit TS type (spatie/typescript-transformer).
     */
    private const array WIRE_TYPE_ATTRIBUTES = ['LiteralTypeScriptType', 'TypeScriptType'];

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
     * Is this expression immediately re-coerced back to a bare string by its parent
     * — `<expr>->toString()` or `(string) <expr>`? The tell that a typed accessor's
     * value is being flattened at the call site instead of read through a named getter.
     */
    public function isReCoercedToString(): bool
    {
        $parent = $this->parent()->node;

        if ($parent instanceof MethodCall
            && $parent->var === $this->node
            && $parent->name instanceof Identifier
            && $parent->name->toString() === 'toString') {
            return true;
        }

        return $parent instanceof Cast\String_ && $parent->expr === $this->node;
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
     * Is this the expression a `foreach` ITERATES — the collection in its header, not the loop
     * variable and not anything in its body?
     */
    public function isLoopSubject(): bool
    {
        $parent = $this->node?->getAttribute('parent');

        return $parent instanceof Foreach_ && $parent->expr === $this->node;
    }

    /**
     * Does this expression answer absence with an EMPTY COLLECTION — a `?? []` or a `?: []`? The
     * shape that turns "there may be nothing here" into "here is nothing", silently, inside
     * whatever expression it sits in.
     */
    public function fallsBackToEmptyCollection(): bool
    {
        if ($this->isCoalesce()) {
            return $this->coalesceRight()->isEmptyArrayLiteral();
        }

        return $this->node instanceof Ternary
            && $this->node->if === null
            && new self($this->node->else)->isEmptyArrayLiteral();
    }

    /**
     * The value an absence-fallback TESTS — the left of a `??`, the condition of a `?:`. The
     * companion to {@see fallsBackToEmptyCollection}, so a caller can ask WHOSE absence is being
     * answered without caring which of the two spellings said it. An empty node when this is neither.
     */
    public function fallbackSubject(): self
    {
        if ($this->isCoalesce()) {
            return $this->coalesceLeft();
        }

        return new self($this->node instanceof Ternary && $this->node->if === null ? $this->node->cond : null);
    }

    /**
     * Does this CLASS do WORK while it is being built — does its constructor ask a collaborator or a
     * static to do something, rather than just accepting what it was handed? Construction should
     * establish what an object IS. A constructor that reaches out makes merely HAVING the object
     * depend on a network, a database or a clock, so it cannot be built in a test, and its side
     * effects fire at a moment the caller never chose.
     *
     * A method call on `$this` does not count — a constructor calling its own private helper is
     * still only assembling itself. Nor does `new`: BUILDING a collaborator is construction, where
     * asking an existing one for something is work.
     */
    public function constructorHasSideEffect(): bool
    {
        $constructor = $this->getConstructor();

        if (! $constructor instanceof ClassMethod) {
            return false;
        }

        $parameters = [];

        foreach ($constructor->params as $param) {
            if (AstNode::variableNameOf($param->var) !== null) {
                $parameters[$param->var->name] = true;
            }
        }

        foreach (new NodeFinder()->find([$constructor], self::isOutwardCall(...)) as $call) {
            if (self::asksACollaborator($call, $parameters) && new self($call)->resultIsDiscarded()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Is this an instance being told to do something — a method call on some object? Static calls
     * are out of scope here: a named constructor reads like a factory that does real work, and the
     * infrastructure ones belong to the rules that already name them.
     */
    private static function isOutwardCall(Node $node): bool
    {
        return AstNode::isMethodSend($node);
    }

    /**
     * Is this call acting on a COLLABORATOR — one the constructor was handed, or one already held as
     * a field? Everything else in a constructor is assembly: a `$this->…` helper (and a fluent run of
     * them) is the object putting itself together, `new Icon($x)->size(…)` is a value being built and
     * configured, and a plain function is a computation.
     *
     * @param  array<string, true>  $parameters  the constructor's own parameter names
     */
    private static function asksACollaborator(Node $call, array $parameters): bool
    {
        $receiver = self::receiverRootOf($call);

        if (AstNode::variableNameOf($receiver) !== null) {
            return isset($parameters[$receiver->name]);
        }

        return $receiver instanceof PropertyFetch && new self($receiver->var)->isThisVariable();
    }

    /**
     * What a call is ultimately received BY — following a fluent chain back to whatever started it.
     */
    private static function receiverRootOf(Node $call): ?Node
    {
        $receiver = AstNode::isMethodSend($call) ? $call->var : null;

        while (AstNode::isMethodSend($receiver)) {
            $receiver = $receiver->var;
        }

        return $receiver;
    }

    /**
     * Is this the `$this` variable?
     */
    public function isThisVariable(): bool
    {
        return self::variableNameOf($this->node) === 'this';
    }

    /**
     * Is this a call ON `$this` — the one receiver whose type is certain without resolving
     * anything, which is why so many analyses either single it out or exclude it.
     */
    public function isThisCall(): bool
    {
        return $this->node instanceof MethodCall && new self($this->node->var)->isThisVariable();
    }

    /**
     * Is this a write to a STATIC property — `self::$table = …`, `static::$seen++`, `Rates::$table = []`?
     * State that outlives every instance and belongs to no one: whoever writes last wins, order of
     * execution becomes load-bearing, and one test leaks into the next.
     *
     * A `??=` does not count. Filling a memo (`self::$parsed[$key] ??= …`) adds no state a caller can
     * observe — ask twice, get the same answer either way. Nor does a declaration's own initialiser,
     * which is not a write at all.
     */
    public function isStaticStateWrite(): bool
    {
        $node = $this->node;

        if ($node instanceof PostInc || $node instanceof PreInc || $node instanceof PostDec || $node instanceof PreDec) {
            return $node->var instanceof StaticPropertyFetch;
        }

        if (! ($node instanceof Assign || $node instanceof AssignOp) || $node instanceof CoalesceAssign) {
            return false;
        }

        $target = self::staticTargetOf($node->var);

        return $target !== null && ! $this->memoisesThatStatic($target);
    }

    /**
     * Is this write just FILLING a memo — does the enclosing function test that very static for
     * presence before writing it (`if (self::$brands === null)`, an early `return` on
     * `isset(self::$cache[$k])`)? Memoisation adds nothing a caller can observe: ask twice, get the
     * same answer either way, and the only thing the static holds is the answer it already gave.
     *
     * The presence test is what separates a memo from a tally. `self::$count = self::$count + 1`
     * also reads before it writes, but it asks what the value IS rather than whether it is THERE —
     * so it stays a global that changes, which is the sin.
     */
    private function memoisesThatStatic(StaticPropertyFetch $target): bool
    {
        $function = $this->enclosingFunction();
        $name = $target->name instanceof Identifier ? $target->name->toString() : null;

        if ($function === null || $name === null) {
            return false;
        }

        foreach (new NodeFinder()->find([$function], self::isPresenceTest(...)) as $test) {
            foreach (new NodeFinder()->findInstanceOf([$test], StaticPropertyFetch::class) as $read) {
                if ($read->name instanceof Identifier && $read->name->toString() === $name) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Is this expression asking whether something is THERE — `isset`, `empty`, `array_key_exists`,
     * or a comparison against `null`?
     */
    private static function isPresenceTest(Node $node): bool
    {
        if ($node instanceof Isset_ || $node instanceof Empty_) {
            return true;
        }

        if ($node instanceof FuncCall && $node->name instanceof Name && $node->name->toLowerString() === 'array_key_exists') {
            return true;
        }

        return ($node instanceof Identical || $node instanceof NotIdentical)
            && (new self($node->left)->isNull() || new self($node->right)->isNull());
    }

    /**
     * The static property a write LANDS on, seeing through an index (`self::$rows['k'] = …` still
     * writes `self::$rows`). Null when the target is not static state.
     */
    private static function staticTargetOf(Node $target): ?StaticPropertyFetch
    {
        while ($target instanceof ArrayDimFetch) {
            $target = $target->var;
        }

        return $target instanceof StaticPropertyFetch ? $target : null;
    }

    /**
     * Does this CLASS change what it IS after it has been built — a write, from some method other
     * than the constructor, to a field the constructor established? Two holders of the same value
     * must be able to rely on it staying that value, and one such write breaks all of them at once.
     *
     * Only CONSTRUCTED fields count, which is what separates a value from a machine made of scalars.
     * A field declared with a default and never taken by the constructor (`private int $offset = 0`)
     * is working state — a cursor, a tally, a buffer — and advancing it is the object doing its job,
     * not a value changing underfoot. A class with no constructor holds no value at all.
     *
     * Two further writes do not count. A `??=` fills a lazily-computed field, which changes nothing a
     * caller can observe. And a mutation inside a method that returns `$this` is a fluent BUILDER
     * accumulating — a recognised, deliberate shape, not a value corrupted.
     */
    public function mutatesOwnFieldsAfterConstruction(): bool
    {
        if (! $this->node instanceof ClassLike) {
            return false;
        }

        $constructed = $this->constructedFieldNames();

        if ($constructed === []) {
            return false;
        }

        // EVERY field must be identity. One field the constructor doesn't take — a `$failures = 0`,
        // a cursor, a `new NullView` handed to itself — means this object keeps working state, and a
        // thing with working state is a machine that happens to be made of values, not a value.
        foreach ($this->fields() as $field) {
            if (! isset($constructed[$field->name])) {
                return false;
            }
        }

        foreach ($this->node->getMethods() as $method) {
            if ($method->name->toString() === '__construct' || self::returnsThis($method)) {
                continue;
            }

            foreach (new NodeFinder()->find([$method], self::isFieldWrite(...)) as $write) {
                if (isset($constructed[self::selfPropertyOf($write->var) ?? ''])) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * The fields the CALLER established — promoted parameters, and fields the constructor assigns
     * straight from a parameter. These are what the object was asked to be.
     *
     * A field the constructor computes for itself (`$this->view = new NullView`) is NOT identity: the
     * caller never said it, so it is the object's own starting state.
     *
     * @return array<string, true>
     */
    private function constructedFieldNames(): array
    {
        $constructor = $this->getConstructor();

        if (! $constructor instanceof ClassMethod) {
            return [];
        }

        $names = [];
        $parameters = [];

        foreach ($constructor->params as $param) {
            if (! $param->var instanceof Variable || ! is_string($param->var->name)) {
                continue;
            }

            $parameters[$param->var->name] = true;

            if ($param->flags !== 0) {
                $names[$param->var->name] = true; // promoted — the parameter IS the field
            }
        }

        foreach (new NodeFinder()->findInstanceOf([$constructor], Assign::class) as $assign) {
            $name = self::selfPropertyOf($assign->var);

            if ($name !== null && $assign->expr instanceof Variable && isset($parameters[$assign->expr->name])) {
                $names[$name] = true;
            }
        }

        return $names;
    }

    /**
     * Does this method hand back `$this` — the mark of a fluent call meant to be chained?
     */
    private static function returnsThis(ClassMethod $method): bool
    {
        return new NodeFinder()->findFirst(
            [$method],
            static fn (Node $node): bool => $node instanceof Return_
                && $node->expr instanceof Variable
                && $node->expr->name === 'this',
        ) !== null;
    }

    /**
     * Is this node a write that REPLACES what a field holds — `=` or a compound `+=`/`.=`, but never
     * `??=`, which only fills a blank that was always going to be filled that way.
     */
    private static function isFieldWrite(Node $node): bool
    {
        return ($node instanceof Assign || $node instanceof AssignOp)
            && ! $node instanceof CoalesceAssign
            && $node->var instanceof PropertyFetch;
    }

    /**
     * Is this `instanceof` the HEAD of a type switch — the first of two or more type tests on the
     * SAME subject, each deciding a different branch of the same function? The shape that asks a
     * value what it is instead of telling it what to do, whatever it is spelled as: an `if`/`elseif`
     * ladder, a run of sequential `if`s that each return, or a `match (true)` over arms.
     *
     * Two tests in ONE condition (`$x instanceof A || $x instanceof B`) are a single question about
     * membership, not a switch — they share a branch point, so they count once. Only the earliest
     * test reports, so one switch yields one finding however many arms it has.
     */
    public function isTypeSwitchHead(): bool
    {
        if (! $this->node instanceof Instanceof_) {
            return false;
        }

        $function = $this->enclosingFunction();
        $ownBranchPoint = self::branchPointOf($this->node);

        if ($function === null || $ownBranchPoint->isNone()) {
            return false;
        }

        $subject = new self($this->node->expr)->exactHash();
        $branchPoints = [];
        $classes = [];

        foreach (new NodeFinder()->findInstanceOf([$function], Instanceof_::class) as $test) {
            $found = self::branchPointOf($test);

            if ($found->isNone()) {
                continue;
            }

            $branchPoint = $found->unwrap();

            // `if ($a instanceof L && $b instanceof L)` switches on a PAIR: two subjects, one set of
            // branches, one sin. Whichever test comes first speaks for it.
            if ($branchPoint === $ownBranchPoint->unwrap() && $test->getStartFilePos() < $this->node->getStartFilePos()) {
                return false;
            }

            if (new self($test->expr)->exactHash() !== $subject) {
                continue;
            }

            if ($test->getStartFilePos() < $this->node->getStartFilePos()) {
                return false; // an earlier test on this subject already heads the switch
            }

            $branchPoints[spl_object_id($branchPoint)] = true;
            $classes[$test->class instanceof Name ? $test->class->toString() : ''] = true;
        }

        return count($branchPoints) >= 2 && count($classes) >= 2;
    }

    /**
     * The classes a type switch headed by this node tests — read on an {@see isTypeSwitchHead} node,
     * so a caller can ask whether the codebase OWNS them. It only owns a fix if it owns every one:
     * you cannot give `DOMText` a method.
     *
     * @return list<string>
     */
    public function typeSwitchClasses(): array
    {
        if (! $this->node instanceof Instanceof_) {
            return [];
        }

        $function = $this->enclosingFunction();

        if ($function === null) {
            return [];
        }

        $subject = new self($this->node->expr)->exactHash();
        $classes = [];

        foreach (new NodeFinder()->findInstanceOf([$function], Instanceof_::class) as $test) {
            if (self::branchPointOf($test)->isNone() || new self($test->expr)->exactHash() !== $subject) {
                continue;
            }

            $classes[$test->class instanceof Name ? $test->class->toString() : ''] = true;
        }

        return array_keys($classes);
    }

    /**
     * The branch this expression DECIDES — the nearest enclosing `if`/`elseif`/ternary whose condition
     * it forms part of, or the `match` arm it guards. Null when it decides nothing (a returned
     * boolean, an argument, a condition inside some branch's body rather than its head). Two tests
     * sharing one branch point are one question asked once.
     *
     * @return Option<Node>
     */
    private static function branchPointOf(Node $test): Option
    {
        $child = $test;
        $parent = $child->getAttribute('parent');

        while ($parent instanceof Node && ! $parent instanceof FunctionLike) {
            if (self::isConditionOf($parent, $child)) {
                return Option::some($parent);
            }

            $child = $parent;
            $parent = $parent->getAttribute('parent');
        }

        return Option::none();
    }

    /**
     * Is this a `for` whose step does NOT advance a counter — one that assigns the next thing
     * instead (`$one = $one instanceof Traceable ? $one->getAbove() : null`)? A `for` promises
     * init-test-step over an induction variable, so its header can be read at a glance; a step that
     * computes where to go next is a WALK, and it turns all three clauses into a puzzle. Every
     * counted spelling is a step — `$i++`, `++$i`, `$i--`, `$i += 2` — so a genuine counted loop
     * never qualifies, however its bound is worked out. A bare `for (;;)` has no step to judge.
     */
    public function isNonCountingFor(): bool
    {
        if (! $this->node instanceof For_ || $this->node->loop === []) {
            return false;
        }

        foreach ($this->node->loop as $step) {
            if (self::advancesACounter($step)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Does this step expression move a counter along — the four spellings of "one more" (`$i++`,
     * `++$i`, `$i--`, `--$i`) plus a compound `$i += n` / `$i -= n`?
     */
    private static function advancesACounter(Node $step): bool
    {
        return $step instanceof PostInc
            || $step instanceof PreInc
            || $step instanceof PostDec
            || $step instanceof PreDec
            || $step instanceof PlusAssign
            || $step instanceof MinusAssign;
    }

    /**
     * Is this a ternary — the full `a ? b : c` or the short `a ?: c`?
     */
    public function isTernary(): bool
    {
        return $this->node instanceof Ternary;
    }

    /**
     * Is this a short-circuiting logical operator — `&&`, `||`, and their low-precedence
     * `and`/`or` spellings? The operators that evaluate their right side CONDITIONALLY, so
     * one written where a value is discarded ({@see resultIsDiscarded}) is branching, not
     * a boolean.
     */
    public function isShortCircuit(): bool
    {
        return $this->node instanceof BooleanAnd
            || $this->node instanceof BooleanOr
            || $this->node instanceof LogicalAnd
            || $this->node instanceof LogicalOr;
    }

    /**
     * The left-hand side of a short-circuit ({@see isShortCircuit}) — the CONDITION when the
     * operator branches. An empty node when this isn't one.
     */
    public function shortCircuitLeft(): self
    {
        return new self($this->isShortCircuit() ? $this->node->left : null);
    }

    /**
     * The right-hand side of a short-circuit ({@see isShortCircuit}) — the guarded work when
     * the operator branches. An empty node when this isn't one.
     */
    public function shortCircuitRight(): self
    {
        return new self($this->isShortCircuit() ? $this->node->right : null);
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
     * Every node ABOVE this one, innermost first, until the tree runs out — the climb "what is this
     * inside of?", which half a dozen predicates were each writing out by hand.
     *
     * @return iterable<self>
     */
    public function ancestors(): iterable
    {
        foreach (self::ancestorsOf($this->node) as $node) {
            yield new self($node);
        }
    }

    /**
     * The ancestors above this node that are still inside the SAME function — the climb stops at the
     * function boundary, since "what am I inside of" almost never means the caller.
     *
     * @return iterable<Node>
     */
    public static function ancestorsWithinFunction(?Node $node): iterable
    {
        foreach (self::ancestorsOf($node) as $ancestor) {
            if ($ancestor instanceof FunctionLike) {
                return;
            }

            yield $ancestor;
        }
    }

    /**
     * Is $subject the CONDITION $parent decides on — the test of an `if`/`elseif`/`while`/`do`, the
     * question a ternary or a `match` asks, or one of a match arm's? Every branching construct keeps
     * its test in the same place, so naming the construct first was the long way round.
     */
    public static function isConditionOf(?Node $parent, Node $subject): bool
    {
        if ($parent instanceof MatchArm) {
            return in_array($subject, $parent->conds ?? [], true);
        }

        return ($parent instanceof If_
            || $parent instanceof ElseIf_
            || $parent instanceof While_
            || $parent instanceof Do_
            || $parent instanceof Ternary
            || $parent instanceof Match_)
            && $parent->cond === $subject;
    }

    /**
     * The same climb over a RAW php-parser node, for the analyses that hold one rather than a match
     * ({@see Support\TypeResolver}, {@see ValueFlow}). The walk lives once; a caller says which
     * ancestor it is looking for, never how to reach it.
     *
     * @return iterable<Node>
     */
    public static function ancestorsOf(?Node $node): iterable
    {
        $current = $node?->getAttribute('parent');

        while ($current instanceof Node) {
            yield $current;

            $current = $current->getAttribute('parent');
        }
    }

    /**
     * This node and then every node above it — for a question a node can answer about ITSELF as well
     * as about what encloses it ("am I, or am I inside, an argument?").
     *
     * @return iterable<self>
     */
    public function selfAndAncestors(): iterable
    {
        yield $this;
        yield from $this->ancestors();
    }

    /**
     * The same climb, starting AT a raw php-parser node — the static twin of
     * {@see selfAndAncestors}, for an analysis whose answer may be the node it was handed
     * ("is this call, or a call it sits inside, a route group?").
     *
     * @return iterable<Node>
     */
    public static function selfAndAncestorsOf(?Node $node): iterable
    {
        if (! $node instanceof Node) {
            return;
        }

        yield $node;
        yield from self::ancestorsOf($node);
    }

    /**
     * Every LINK of a method-call chain, outermost first, down to the expression it is rooted in:
     * `Route::name('admin.')->prefix('x')->group(…)` yields the `group` call, the `prefix` call,
     * the `name` call, then the `Route::` static call. A fact can be carried on any link, so a
     * caller that only looked at the outermost one read half the chain.
     *
     * @return iterable<Node>
     */
    public static function chainLinks(?Node $node): iterable
    {
        while ($node instanceof Node) {
            yield $node;

            $node = $node instanceof MethodCall || $node instanceof NullsafeMethodCall ? $node->var : null;
        }
    }

    /**
     * What a method-call chain is rooted IN — the static call, `new`, variable or literal the
     * `->` links hang off. Null for no node at all.
     */
    public static function chainRootOf(?Node $node): ?Node
    {
        $root = null;

        foreach (self::chainLinks($node) as $link) {
            $root = $link;
        }

        return $root;
    }

    /**
     * Is this `??` CANCELLED by the equality it sits in — `($x ?? '') !== ''`?
     *
     * The fallback and the thing it is compared against are the same expression, so the branch it
     * chooses says "absent" and "equal to the fallback" with one voice. Whether that conflation is
     * the sin depends on the fallback being a manufactured one, which the caller asks separately —
     * this predicate answers only the shape.
     *
     * Sameness is asked of {@see StructuralHash}, the engine's own answer to "are these the same
     * expression", so a fallback written differently from the operand it cancels is still caught and
     * a genuine default (`?? 'EUR'`) never matches.
     */
    public function isCancelledCoalesce(): bool
    {
        if (! $this->node instanceof Coalesce) {
            return false;
        }

        $parent = $this->parent()->node;

        if (! $parent instanceof Identical && ! $parent instanceof NotIdentical && ! $parent instanceof Equal && ! $parent instanceof NotEqual) {
            return false;
        }

        $other = $parent->left === $this->node ? $parent->right : $parent->left;

        return StructuralHash::of($other) === StructuralHash::of($this->node->right);
    }

    /**
     * Is this the `null` literal?
     */
    public function isNull(): bool
    {
        return AstNode::isNullConstant($this->node);
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
     * Is this the empty-array literal `[]`? Distinct from {@see isEmptyLiteral}: an empty
     * COLLECTION is the type's own identity ("no items") — a real domain answer — whereas
     * `''`/`0`/`false` impersonate a present scalar. Callers that hunt manufactured fakes
     * exclude it; callers that hunt absence values include it.
     */
    public function isEmptyArrayLiteral(): bool
    {
        return $this->node instanceof Array_ && $this->node->items === [];
    }

    /**
     * The $index'th ARGUMENT of this call, as a node — a null-object when the call has no such
     * argument, so a caller reads `$call->argument(1)->…` without first asking whether it is there.
     */
    public function argument(int $index): self
    {
        $arguments = $this->arguments();

        return new self($arguments[$index]->value ?? null);
    }

    /**
     * Is this expression a LINE separator — a string literal of nothing but newlines, or `PHP_EOL`?
     * What tells "these parts are lines" from "these parts are fields" at a `implode`/`explode`.
     */
    public function isNewlineSeparator(): bool
    {
        if ($this->node instanceof ConstFetch) {
            return $this->node->name->toString() === 'PHP_EOL';
        }

        return $this->node instanceof String_
            && $this->node->value !== ''
            && trim($this->node->value, "\r\n") === '';
    }

    /**
     * The STRING-LITERAL items of an array literal, in order — the parts of it that are fixed text
     * rather than something the program computed.
     *
     * @return list<string>
     */
    public static function literalItemsOf(Array_ $array): array
    {
        $literals = [];

        foreach ($array->items as $item) {
            if ($item instanceof ArrayItem && $item->value instanceof String_) {
                $literals[] = $item->value->value;
            }
        }

        return $literals;
    }

    /**
     * Is this a HOOKED property declaration (`public T $x { get => …; }`) — or a promoted
     * param with hooks? A hooked property is COMPUTED, not a stored slot: hydration never
     * writes it, so slot-shaped advice (e.g. "type it `T | Optional`") does not apply to it.
     */
    public function isHookedProperty(): bool
    {
        return ($this->node instanceof Property && $this->node->hooks !== [])
            || ($this->node instanceof Param && $this->node->hooks !== []);
    }

    /**
     * Is this expression itself a NULL comparison — `$x === null` / `null !== $x` (strict identity against
     * the `null` literal) or `is_null($x)`? The tell that a condition GUARDS on absence, as opposed to an
     * arbitrary boolean test (`$x->relationLoaded('y')`, `$flag`). A `??`/`?->` is a null-guard too, but
     * those are their own node kinds — this is the comparison form a ternary condition takes.
     */
    public function isNullComparison(): bool
    {
        if ($this->node instanceof Identical || $this->node instanceof NotIdentical) {
            return new self($this->node->left)->isNull() || new self($this->node->right)->isNull();
        }

        return $this->node instanceof FuncCall
            && $this->node->name instanceof Name
            && strtolower($this->node->name->toString()) === 'is_null';
    }

    /**
     * Is this expression's result immediately de-nulled by the caller — consumed
     * with `?->`, `?? …`, or compared `=== null` / `!== null`? The tell that a
     * `?T` return is being null-checked at the call site instead of at the source.
     */
    public function isDeNulled(): bool
    {
        $parent = $this->parent()->node;

        if ($this->isNullsafeReceiver() || $this->isCoalesceLeft()) {
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
     * Is this expression consumed in a position that ACKNOWLEDGES it can be null — a null-guard
     * ({@see isDeNulled}: `?->`, `?? `, `=== null` / `!== null`) OR a truthiness test (`if ($x)`,
     * `$x && …`, `$x || …`, `! $x`, `$x ? … : …`, `isset`/`empty`). The terminal "the code knows
     * this may be absent" verdict a value-flow walk stops on. Counting a bare truthiness test is
     * deliberately conservative — for a nullable value it DOES gate the null, and over-counting
     * only suppresses a finding, never invents one.
     */
    public function isNullGuardedUse(): bool
    {
        if ($this->isDeNulled() || $this->isTypeInterrogated()) {
            return true;
        }

        $parent = $this->parent()->node;

        // `isset($x[$k])` / `empty($x[$k])` / `$x[$k] ?? …` — indexing a null base short-circuits
        // safely, so a guarded access GUARDS the base. The isset/coalesce wraps the access, not $x.
        if ($parent instanceof ArrayDimFetch && $parent->var === $this->node) {
            return new self($parent)->isNullGuardedUse();
        }

        return $parent instanceof BooleanNot
            || $parent instanceof BooleanAnd
            || $parent instanceof BooleanOr
            || $parent instanceof Isset_
            || $parent instanceof Empty_
            || ($parent instanceof CoalesceAssign && $parent->var === $this->node)
            || ($parent instanceof If_ && $parent->cond === $this->node)
            || ($parent instanceof While_ && $parent->cond === $this->node)
            || ($parent instanceof Ternary && $parent->cond === $this->node);
    }

    /**
     * Is this value type-interrogated before use — `$x instanceof Y`, `is_string($x)`, `is_null($x)`,
     * `is_array($x)`, …? Like a null-guard, the check ACKNOWLEDGES the value may be absent / another
     * type, so a use gated by it is not an unconditional presence-assumption.
     */
    private function isTypeInterrogated(): bool
    {
        $parent = $this->parent()->node;

        if ($parent instanceof Instanceof_ && $parent->expr === $this->node) {
            return true;
        }

        if ($parent instanceof Arg) {
            $call = $parent->getAttribute('parent');

            return $call instanceof FuncCall
                && $call->name instanceof Name
                && in_array(strtolower($call->name->toString()), self::TYPE_PREDICATES, true);
        }

        return false;
    }

    /**
     * A `$this->field` read whose method OPENS with an early-return/throw guard clause on `$this`
     * state (`if (! $this->started) { return; } … $this->field`). The object gates the read on its
     * own lifecycle, so the field is legitimately conditionally-present — the classic event-sourcing
     * aggregate, not a phantom. Only SELF reads count: an external `$other->field` read (how a plain
     * data carrier is consumed) is unaffected, so a genuine passive-carrier phantom still fires.
     * Over-clearing here only ever MISSES a phantom — the FP-safe direction.
     */
    public function isSelfReadGuardedByStateClause(): bool
    {
        if (! $this->node instanceof PropertyFetch) {
            return false;
        }

        if (! new self($this->node->var)->isThisVariable()) {
            return false;
        }

        $function = $this->enclosingFunction();

        if ($function === null || $function->getStmts() === null) {
            return false;
        }

        $finder = new NodeFinder();

        foreach ($function->getStmts() as $statement) {
            if ($finder->findFirst([$statement], fn (Node $node): bool => $node === $this->node) !== null) {
                return false; // reached the read's own statement — a guard must PRECEDE it
            }

            if (new self($statement)->isStateGuardClause()) {
                return true;
            }
        }

        return false;
    }

    /**
     * An `if (<reads $this state>) { return|throw|continue|break; }` bail-out guard clause.
     */
    private function isStateGuardClause(): bool
    {
        if (! $this->node instanceof If_ || $this->node->else !== null || $this->node->elseifs !== []) {
            return false;
        }

        return self::isBailOut(end($this->node->stmts)) && new self($this->node->cond)->isThisStateRead();
    }

    /**
     * Does this expression read any `$this->…` property?
     */
    private function isThisStateRead(): bool
    {
        return new NodeFinder()->findFirst(
            [$this->node],
            static fn (Node $node): bool => $node instanceof PropertyFetch
                && $node->var instanceof Variable
                && $node->var->name === 'this',
        ) !== null;
    }

    /**
     * Classify what happens to a variable AT THIS occurrence — written, passed,
     * called on, null-checked, returned… The per-stop verdict behind {@see trace}.
     */
    public function interactionKind(): InteractionKind
    {
        $parent = $this->parent()->node;

        return match (true) {
            $this->isAssignmentTarget() => InteractionKind::Assigned,
            ($parent instanceof Identical || $parent instanceof NotIdentical) && $this->isDeNulled() => InteractionKind::NullChecked,
            $this->isCoalesceLeft() => InteractionKind::Coalesced,
            $this->isNullsafeReceiver() => InteractionKind::Nullsafe,
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
     * Is this the LEFT of a `??` — the value being coalesced away?
     */
    public function isCoalesceLeft(): bool
    {
        $parent = $this->parent()->node;

        return $parent instanceof Coalesce && $parent->left === $this->node;
    }

    /**
     * Is this the RECEIVER of a nullsafe access — the `$x` of `$x?->y` or `$x?->y()`?
     */
    public function isNullsafeReceiver(): bool
    {
        $parent = $this->parent()->node;

        return ($parent instanceof NullsafeMethodCall || $parent instanceof NullsafePropertyFetch)
            && $parent->var === $this->node;
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

        return (AstNode::isMethodSend($parent)) && $parent->var === $this->node;
    }

    /**
     * The class name of a `new X(...)`, or null when this is not a `new`.
     */
    public function newClassName(): ?string
    {
        return $this->newClassNode()?->toString();
    }

    /**
     * The class-name node of a `new NamedClass(...)` — for reading the name AS WRITTEN, or null when this isn't one.
     */
    public function newClassNode(): ?Name
    {
        return $this->node instanceof New_ && $this->node->class instanceof Name ? $this->node->class : null;
    }

    /**
     * The resolved class name of a `Class::method(...)` static call, or null. Names are resolved at
     * parse time, so this is fully qualified; `self`/`static` resolve to the enclosing class, so a
     * caller comparing against a real class name never has to special-case them.
     */
    public function staticCallClass(): ?string
    {
        if (! $this->node instanceof StaticCall || ! $this->node->class instanceof Name) {
            return null;
        }

        $class = $this->node->class->toString();

        return in_array($class, ['self', 'static'], true) ? $this->enclosingClassName() : $class;
    }

    /**
     * The method name of a `Class::method(...)` static call, or null.
     */
    public function staticCallMethod(): ?string
    {
        return $this->node instanceof StaticCall && $this->node->name instanceof Identifier
            ? $this->node->name->toString()
            : null;
    }

    /**
     * The method name of a `$obj->method(...)` / `$obj?->method(...)` instance call, or null.
     */
    public function methodCallName(): ?string
    {
        return (AstNode::isMethodSend($this->node)) && $this->node->name instanceof Identifier
            ? $this->node->name->toString()
            : null;
    }

    /**
     * Is this a static call whose (fully-qualified) class begins with $prefix — the readable
     * form of "a call into this namespace", e.g. `Illuminate\Support\Facades\`.
     */
    public function staticCallClassStartsWith(string $prefix): bool
    {
        $class = $this->staticCallClass();

        return $class !== null && str_starts_with($class, $prefix);
    }

    /**
     * Is this a static call to a method named $name — `X::fake()` → `staticCallMethodIs('fake')`.
     */
    public function staticCallMethodIs(string $name): bool
    {
        return $this->staticCallMethod() === $name;
    }

    /**
     * For a function call (`app(...)`/`resolve(...)`), is the first argument a
     * statically-known class reference — a `Foo::class` constant or a string
     * literal? A variable argument is a runtime class-string the container can't
     * be replaced with constructor DI, so it is NOT a literal.
     */
    public function firstArgIsClassLiteral(): bool
    {
        if (! $this->node instanceof FuncCall) {
            return false;
        }

        $first = $this->node->args[0] ?? null;

        if (! $first instanceof Arg) {
            return false;
        }

        return $first->value instanceof ClassConstFetch || $first->value instanceof String_;
    }

    /**
     * The class a function call's first argument names, when it IS a class literal —
     * `app(Foo::class)` → `Foo` (fully qualified), `app('mailer')` → `mailer`, with
     * `static::class`/`self::class` resolved to the enclosing class. Null when the first
     * argument is anything else (a runtime class-string, no args, not a function call).
     */
    public function firstArgClassLiteral(): ?string
    {
        if (! $this->node instanceof FuncCall) {
            return null;
        }

        $first = $this->node->args[0] ?? null;

        if (! $first instanceof Arg) {
            return null;
        }

        if ($first->value instanceof String_) {
            return $first->value->value;
        }

        if ($first->value instanceof ClassConstFetch && $first->value->class instanceof Name) {
            $name = $first->value->class;

            if (in_array($name->toLowerString(), ['static', 'self'], true)) {
                return $this->enclosingClassName();
            }

            return $name->toString();
        }

        return null;
    }

    /**
     * Is this `app()`/`resolve()` call resolving the class it sits IN — `app(static::class)`,
     * `app(self::class)`, or the class's own name? A class constructing ITSELF through the
     * container is a static factory's construction seam (`Foo::make()` giving a static entry
     * point constructor DI), not a dependency reach.
     */
    public function isEnclosingClassResolution(): bool
    {
        $enclosing = $this->enclosingClassName();

        return $enclosing !== null && $this->firstArgClassLiteral() === $enclosing;
    }

    /**
     * Is this node the value of a `return` statement?
     */
    public function isReturnedValue(): bool
    {
        return $this->parent()->node instanceof Return_;
    }

    /**
     * Is this expression the return value of a function — a `return <here>;` or the
     * body of an arrow function (`fn () => <here>`), which returns implicitly?
     */
    public function isReturnExpression(): bool
    {
        $parent = $this->parent()->node;

        return $parent instanceof Return_
            || ($parent instanceof ArrowFunction && $parent->expr === $this->node);
    }

    /**
     * Is this a POSITIONAL TUPLE — a keyless array literal of three-plus items that
     * bundles two-or-more DISTINCT variables (`[$node, $key, $inputs, $outputs]`)?
     * Several independent values smuggled as a list to dodge a value object; the
     * caller must destructure by position, where order is unchecked and unnamed. A
     * single-source projection (`[$row->a, $row->b, $row->c]` — one variable) or a
     * list of literals is a collection, not a tuple, and is left alone. So is a
     * CONCATENATION (`[...$a, ...$b, ...$c]`): a spread contributes however many
     * elements its operand happens to hold, so it occupies no position a caller
     * could destructure — the array's length is not even known at the call site.
     */
    public function isPositionalTuple(): bool
    {
        if (! $this->node instanceof Array_ || count($this->node->items) < 3) {
            return false;
        }

        $variableRoots = [];

        foreach ($this->node->items as $item) {
            if (! $item instanceof ArrayItem || $item->key !== null || $item->unpack) {
                return false;
            }

            $root = self::variableRoot($item->value);

            if ($root !== null) {
                $variableRoots[$root] = true;
            }
        }

        return count($variableRoots) >= 2;
    }

    /**
     * The name of the variable an expression is ultimately rooted in (`$x`,
     * `$x->a->b()`, `$x['k']` all root in `x`), or null when it isn't rooted in a
     * variable (a literal, a static call, a free function call).
     */
    protected static function variableRoot(Node $expr): ?string
    {
        while (true) {
            if ($expr instanceof Variable) {
                return is_string($expr->name) ? $expr->name : null;
            }

            if ($expr instanceof PropertyFetch
                || $expr instanceof NullsafePropertyFetch
                || $expr instanceof MethodCall
                || $expr instanceof NullsafeMethodCall
                || $expr instanceof ArrayDimFetch) {
                $expr = $expr->var;

                continue;
            }

            return null;
        }
    }

    /**
     * The single RECEIVER every value in this array literal is fetched off — `['amount' => $o->cents,
     * 'currency' => $o->code]` shares `$o`. Returns that receiver when EVERY item (two or more) is a
     * property/method fetch off the identical receiver, else null.
     *
     * @return Option<Node>
     */
    public static function sharedFetchReceiver(Array_ $array): Option
    {
        if (count($array->items) < 2) {
            return Option::none();
        }

        $receiver = null;
        $path = null;

        foreach ($array->items as $item) {
            if (! $item instanceof ArrayItem
                || ! ($item->value instanceof PropertyFetch
                    || $item->value instanceof NullsafePropertyFetch
                    || $item->value instanceof MethodCall
                    || $item->value instanceof NullsafeMethodCall)) {
                return Option::none();
            }

            $here = self::fetchPath($item->value->var);

            if ($here === null) {
                return Option::none();
            }

            if ($path === null) {
                $path = $here;
                $receiver = $item->value->var;
            } elseif ($here !== $path) {
                return Option::none();
            }
        }

        return Option::fromNullable($receiver);
    }

    /**
     * A canonical string for a receiver chain (`$this->order`, `$row->wrapper()`), or null when it roots
     * in something that isn't a plain variable/property/method chain.
     */
    private static function fetchPath(Node $expr): ?string
    {
        if ($expr instanceof Variable) {
            return is_string($expr->name) ? '$' . $expr->name : null;
        }

        if (self::isPropertyRead($expr) || self::isMethodSend($expr)) {
            $base = self::fetchPath($expr->var);
            $name = self::memberNameOf($expr);
            $call = self::isMethodSend($expr) ? '()' : '';

            return $base !== null && $name !== null ? "{$base}->{$name}{$call}" : null;
        }

        return null;
    }

    /**
     * How many string-literal keys this array literal has (0 if not an array).
     */
    public function stringKeyCount(): int
    {
        return count($this->stringKeys());
    }

    /**
     * Is this array literal a LOOKUP TABLE rather than a record — a keyed map whose every value is a
     * constant of ONE class (`'dashboard.view' => Permission::SHOPFLOOR, 'orders.index' => …`)?
     *
     * A record's fields DIFFER — that is what makes them fields, and what a value object is for. A
     * table's values are interchangeable: each is the same type, the keys are data rather than
     * names, and `array<string, Permission>` already states the whole thing. There is nothing to
     * name, so there is no value object owed — turning the rows into objects would only make the
     * lookup linear.
     *
     * Deliberately narrow: a class-constant is a closed, already-typed value, so a map of them is a
     * table by construction. A homogeneous map of CONSTRUCTED objects is not covered — those really
     * can be a record whose fields happen to share a type (`['shipping' => new Money(5), 'handling'
     * => new Money(2)]`).
     */
    public function isHomogeneousLookupTable(): bool
    {
        if (! $this->node instanceof Array_ || count($this->node->items) < 2) {
            return false;
        }

        $classes = [];

        foreach ($this->node->items as $item) {
            if (! $item instanceof ArrayItem || ! $item->value instanceof ClassConstFetch || ! $item->value->class instanceof Name) {
                return false;
            }

            $classes[$item->value->class->toString()] = true;
        }

        return count($classes) === 1;
    }

    /**
     * The string keys of this array literal — `['type' => …, 'properties' => …]`
     * yields `['type', 'properties']`. Empty when not an array literal.
     *
     * @return list<string>
     */
    public function stringKeys(): array
    {
        if (! $this->node instanceof Array_) {
            return [];
        }

        $keys = [];

        foreach ($this->node->items as $item) {
            if ($item instanceof ArrayItem && $item->key instanceof String_) {
                $keys[] = $item->key->value;
            }
        }

        return $keys;
    }

    /**
     * Is this array literal a JSON-Schema / external-contract node rather than a domain
     * record? JSON Schema is a recursive, open-ended map keyed by the schema vocabulary
     * (`type`/`properties`/`enum`/`items`/`required`/`description`/…), serialized straight
     * to a provider or agent — not a fixed-field bag, and not sensibly a typed value
     * object (it's the full recursive spec). The value-objects skill's serialization-shape
     * exemption. The signal is the vocabulary itself, not a method name:
     *
     *  - a STRUCTURAL keyword (`properties`/`items`/`enum`/`required`/`additionalProperties`)
     *    is present — an unambiguous schema container; or
     *  - a `type` field whose literal value is a JSON primitive (`object`/`string`/…) — or
     *    is computed, so not a domain constant — paired with another schema keyword.
     *
     * A domain bag whose `type` is a domain constant (`['type' => 'book', …]`) is NOT a
     * schema and stays flagged.
     */
    public function looksLikeJsonSchema(): bool
    {
        if (! $this->node instanceof Array_) {
            return false;
        }

        $keys = $this->stringKeys();
        $structural = ['properties', 'items', 'enum', 'required', 'additionalProperties'];

        if (array_intersect($structural, $keys) !== []) {
            return true;
        }

        if (! in_array('type', $keys, true)) {
            return false;
        }

        $type = $this->literalForKey('type');

        // A literal `type` that is a domain constant (not a JSON primitive) is a bag.
        if ($type !== null && ! in_array($type, ['object', 'array', 'string', 'integer', 'number', 'boolean', 'null'], true)) {
            return false;
        }

        // `type` (primitive or computed) plus any other schema keyword = a schema node.
        return array_intersect(['description', 'format', 'title', 'default', 'nullable'], $keys) !== [];
    }

    /**
     * The literal string value bound to string key $key in this array literal, or null
     * when the key is absent or its value isn't a plain string literal.
     */
    protected function literalForKey(string $key): ?string
    {
        if (! $this->node instanceof Array_) {
            return null;
        }

        foreach ($this->node->items as $item) {
            if ($item instanceof ArrayItem
                && $item->key instanceof String_
                && $item->key->value === $key
                && $item->value instanceof String_) {
                return $item->value->value;
            }
        }

        return null;
    }

    /**
     * The `__construct` declaration of the class this node is in (or IS), or null where the class
     * declares none. The one place `getMethod('__construct')` lives — read the constructor through
     * this, or through {@see fromConstructor} to act on one without the null check.
     */
    public function getConstructor(): ?ClassMethod
    {
        return self::constructorOf($this->enclosingClass());
    }

    /**
     * $with applied to the constructor, or null when the class declares none — the reading form,
     * for a caller that wants something OUT of the constructor and has nothing to say about a
     * class without one.
     *
     * @template T
     *
     * @param  \Closure(ClassMethod): T  $with
     * @return T|null
     */
    public function fromConstructor(\Closure $with): mixed
    {
        $constructor = $this->getConstructor();

        return $constructor === null ? null : $with($constructor);
    }

    /**
     * The constructor parameters of the class this node is in (or IS) — promoted and plain, in source
     * order; an empty list when there is no constructor.
     *
     * @return list<Param>
     */
    public function constructorParams(): array
    {
        return self::constructorParamsOf($this->enclosingClass());
    }

    /**
     * The `__construct` of a raw class-like node (or null) — the static seam for code that already
     * holds a {@see ClassLike} (a scribe, a shape analyser) rather than an {@see AstNode}.
     */
    public static function constructorOf(?ClassLike $class): ?ClassMethod
    {
        return $class?->getMethod('__construct');
    }

    /**
     * The constructor parameters of a raw class-like node — promoted and plain, in source order.
     *
     * @return list<Param>
     */
    public static function constructorParamsOf(?ClassLike $class): array
    {
        return self::constructorOf($class)?->params ?? [];
    }

    /**
     * For a class declaration: does its constructor promote at least one property and is
     * EVERY promoted property NULLABLE? Such a record's type tells no truth about what's
     * required — every field admits null, so a consumer must re-validate what `::from()`
     * should have guaranteed.
     *
     * A non-nullable property with a typed default (`int $x = 0`, `string $s = ''`) is NOT
     * counted as a lie: it is an HONEST optional (a value object / accumulator with a
     * sensible identity), and its presence means the class is not all-nullable. Only
     * `?T`/`T|null` fields are the costume this flags.
     */
    public function everyConstructorParamNullable(): bool
    {
        if (! $this->node instanceof Class_) {
            return false;
        }

        $constructor = $this->getConstructor();

        if ($constructor === null) {
            return false;
        }

        $promoted = array_filter($constructor->params, static fn (Param $param): bool => $param->flags !== 0);

        if ($promoted === []) {
            return false;
        }

        foreach ($promoted as $param) {
            if (! TypeName::isNullable($param->type)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Can this class be constructed with `new T()` — no constructor, or one whose every
     * parameter is defaulted or variadic (nothing must be supplied)? That makes `new T()`
     * a valid constant-expression default, and names the type's own resting state: the
     * honest Null Object to default an optional field to instead of `null`.
     */
    public function constructorRequiresNoArguments(): bool
    {
        if (! $this->node instanceof Class_) {
            return false;
        }

        $constructor = $this->getConstructor();

        if ($constructor === null) {
            return true;
        }

        foreach ($constructor->params as $param) {
            if (self::isRequiredParam($param)) {
                return false;
            }
        }

        return true;
    }

    /**
     * The names of this class's PUBLIC fields — its public promoted constructor
     * params and its public declared properties. That is the shape a Data class
     * publishes as its payload; a non-class node has none.
     *
     * @return list<string>
     */
    public function publicFieldNames(): array
    {
        if (! $this->node instanceof Class_) {
            return [];
        }

        $names = [];

        foreach ($this->constructorParams() as $param) {
            if (($param->flags & Modifiers::PUBLIC) !== 0 && AstNode::variableNameOf($param->var) !== null) {
                $names[] = $param->var->name;
            }
        }

        foreach ($this->node->getProperties() as $property) {
            if ($property->isPublic()) {
                foreach ($property->props as $declared) {
                    $names[] = $declared->name->toString();
                }
            }
        }

        return array_values(array_unique($names));
    }

    /**
     * Every field this class declares — its promoted constructor parameters and its declared
     * properties, each as a {@see ClassField} carrying name, type, attributes, visibility, and
     * whether it was promoted. The generic shape a framework decorator reads to apply policy
     * (which fields serialise, which carry an injection attribute). A non-class node has none.
     *
     * @return list<ClassField>
     */
    public function fields(): array
    {
        $class = $this->enclosingClass();

        if ($class === null) {
            return [];
        }

        $fields = [];

        foreach (self::constructorParamsOf($class) as $param) {
            if (self::promotedParamNameOf($param) !== null) {
                $fields[] = new ClassField(
                    name: $param->var->name,
                    type: $param->type,
                    attributeGroups: $param->attrGroups,
                    isPublic: ($param->flags & Modifiers::PUBLIC) !== 0,
                    isPromoted: true,
                    docComment: $param->getDocComment()?->getText(),
                );
            }
        }

        foreach ($class->getProperties() as $property) {
            foreach ($property->props as $declared) {
                $fields[] = new ClassField(
                    name: $declared->name->toString(),
                    type: $property->type,
                    attributeGroups: $property->attrGroups,
                    isPublic: $property->isPublic(),
                    isPromoted: false,
                    docComment: $property->getDocComment()?->getText(),
                );
            }
        }

        return $fields;
    }

    /**
     * The fully-qualified CLASS names this node's docblock cross-links via `{@see …}` / `{@link …}` — only
     * NAMESPACED references (containing a `\`), each with its leading `\` and any `::member` tail stripped, so
     * `{@see \App\Foo::bar}` yields `App\Foo` and a bare `{@see doThing()}` is ignored. The one home for
     * reading a docblock's cross-references.
     *
     * @return list<string>
     */
    public function docReferences(): array
    {
        $doc = $this->node?->getDocComment()?->getText();

        if ($doc === null) {
            return [];
        }

        preg_match_all('/\{@(?:see|link)\s+\\\\?([A-Za-z_][\w\\\\]*\\\\[\w\\\\]+)/', $doc, $matches);

        return array_values(array_unique($matches[1]));
    }

    /**
     * This node read as a single {@see ClassField}, when it IS one field declaration — a promoted
     * constructor parameter, or a declared property (its first declared name). The absence rides in
     * the type: every reader has to say what it means for a node not to be a field.
     *
     * @return Option<ClassField>
     */
    public function asField(): Option
    {
        if ($this->node instanceof Param && $this->node->flags !== 0 && AstNode::variableNameOf($this->node->var) !== null) {
            return Option::some(new ClassField(
                name: $this->node->var->name,
                type: $this->node->type,
                attributeGroups: $this->node->attrGroups,
                isPublic: ($this->node->flags & Modifiers::PUBLIC) !== 0,
                isPromoted: true,
                docComment: $this->node->getDocComment()?->getText(),
            ));
        }

        if ($this->node instanceof Property && $this->node->props !== []) {
            return Option::some(new ClassField(
                name: $this->node->props[0]->name->toString(),
                type: $this->node->type,
                attributeGroups: $this->node->attrGroups,
                isPublic: $this->node->isPublic(),
                isPromoted: false,
                docComment: $this->node->getDocComment()?->getText(),
            ));
        }

        return Option::none();
    }

    /**
     * Is this an assignment to one of `$this`'s properties — `$this->foo = …`?
     */
    public function isThisPropertyAssignment(): bool
    {
        return $this->node instanceof Assign
            && $this->node->var instanceof PropertyFetch
            && $this->node->var->var instanceof Variable
            && $this->node->var->var->name === 'this'
            && $this->node->var->name instanceof Identifier;
    }

    /**
     * The property name a `$this->foo = …` assignment targets, or null when this isn't one.
     */
    public function assignedPropertyName(): ?string
    {
        return $this->isThisPropertyAssignment() ? $this->node->var->name->toString() : null;
    }

    /**
     * Does the right-hand side of this assignment read a variable other than `$this` — a local or a
     * parameter? Such an expression can't move into a property-hook getter, which sees only `$this`.
     */
    public function assignmentReferencesLocalVariable(): bool
    {
        if (! $this->node instanceof Assign) {
            return false;
        }

        foreach (new NodeFinder()->findInstanceOf([$this->node->expr], Variable::class) as $variable) {
            if ($variable->name !== 'this') {
                return true;
            }
        }

        return false;
    }

    /**
     * Is this node nested inside a branch — an `if`/`match`/ternary or a loop — within its enclosing
     * function? A statement guarded by control flow is not a straight-line assignment.
     */
    public function isWithinBranch(): bool
    {
        foreach ($this->ancestors() as $ancestor) {
            if ($ancestor->node instanceof FunctionLike) {
                break;
            }

            if ($ancestor->isBranchingConstruct()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Is this node itself a construct that CHOOSES — a conditional, a match, or a loop? The shapes
     * that make whatever sits inside them conditional rather than certain.
     */
    public function isBranchingConstruct(): bool
    {
        return $this->node instanceof If_
            || $this->node instanceof Match_
            || $this->node instanceof Ternary
            || $this->node instanceof Foreach_
            || $this->node instanceof For_
            || $this->node instanceof While_
            || $this->node instanceof Do_;
    }

    /**
     * Is this node a function or method declaration (not a closure)?
     */
    public function isFunctionDeclaration(): bool
    {
        return $this->node instanceof ClassMethod || $this->node instanceof Function_;
    }

    /**
     * The `//` (and `#`) comments attached to this node — the inline prose a reader meets immediately
     * before this statement. One entry per comment line, each still carrying its marker. Neither a
     * docblock nor a slash-star block counts: both are documentation constructs, judged as such
     * elsewhere.
     *
     * @return list<string>
     */
    public function lineComments(): array
    {
        $comments = [];

        foreach ($this->node?->getComments() ?? [] as $comment) {
            $text = $comment->getText();

            if (! $comment instanceof Doc && (str_starts_with($text, '//') || str_starts_with($text, '#'))) {
                $comments[] = $text;
            }
        }

        return $comments;
    }

    /**
     * Is this class member declared BELOW a method of the same class — state a reader only meets after
     * the behaviour that uses it? False for a node outside a class body, and for the methods themselves.
     */
    public function isBelowAMethodInItsClass(): bool
    {
        $seenMethod = false;

        foreach ($this->enclosingClass()?->stmts ?? [] as $member) {
            if ($member === $this->node) {
                return $seenMethod;
            }

            $seenMethod = $seenMethod || $member instanceof ClassMethod;
        }

        return false;
    }

    /**
     * Is this class member declared out of the canonical head order — does something above it belong
     * below it? {@see \JesseGall\CodeCommandments\Ast\Support\ClassLayoutOrder}
     */
    public function breaksClassLayoutOrder(): bool
    {
        $class = $this->enclosingClass();

        return $class !== null && $this->node !== null && ClassLayoutOrder::isOutOfOrder($class, $this->node);
    }

    /**
     * The FIRST method declared by the class this node belongs to — the line every declaration must
     * stand above. Null outside a class, or in one that declares no method at all.
     */
    public function firstMethodOfItsClass(): ?ClassMethod
    {
        foreach ($this->enclosingClass()?->stmts ?? [] as $member) {
            if ($member instanceof ClassMethod) {
                return $member;
            }
        }

        return null;
    }

    /**
     * This method declaration's name, or null when this node is not one.
     */
    public function methodName(): ?string
    {
        return $this->node instanceof ClassMethod ? $this->node->name->toString() : null;
    }

    /**
     * Is this a magic method — a name the LANGUAGE dictates (`__construct`, `__invoke`, `__toString`)?
     * Its mood is not the author's to choose.
     */
    public function isMagicMethod(): bool
    {
        $name = $this->methodName();

        return $name !== null && str_starts_with($name, '__');
    }

    /**
     * Does this arrow function declare a return type at all?
     */
    public function hasReturnType(): bool
    {
        return $this->node instanceof ArrowFunction && $this->node->returnType !== null;
    }

    /**
     * Does this method declare a `bool` return — an answer to a question, whatever its name says?
     */
    public function returnsBool(): bool
    {
        return $this->declaredReturnType() === 'bool';
    }

    /**
     * Is this method a COMMAND — declared to return nothing, or to hand back the object it acted on
     * (`void`, `never`, `static`, `self`, `$this`)? A command is told to do something, so its name is
     * an order; anything that answers with a value is a question or a getter instead.
     */
    public function isCommandMethod(): bool
    {
        $type = $this->declaredReturnType();

        if (in_array($type, ['void', 'never'], true)) {
            return true;
        }

        // A self-return is fluent — an order you can chain — but only from an INSTANCE. The same
        // signature on a static method is a named constructor (`ScanResult::requiresSwitch(...)`),
        // which names the thing it builds and was never an order.
        return in_array($type, ['static', 'self', '$this'], true) && ! $this->isStaticMethod();
    }

    /**
     * Is this method declared `static`?
     */
    public function isStaticMethod(): bool
    {
        return $this->node instanceof ClassMethod && $this->node->isStatic();
    }

    /**
     * The declared return type spelled as written, or null when the method declares none — an untyped
     * method says nothing about its mood, so a rule reading this leaves it alone.
     */
    public function declaredReturnType(): ?string
    {
        if (! $this->node instanceof ClassMethod) {
            return null;
        }

        $type = $this->node->getReturnType();

        return $type instanceof Identifier || $type instanceof Name ? strtolower($type->toString()) : null;
    }

    /**
     * Does this method declaration take no arguments — so whatever it answers, it answers about the
     * receiver alone?
     */
    public function takesNoArguments(): bool
    {
        return $this->node instanceof ClassMethod && $this->node->params === [];
    }

    /**
     * Every DOCBLOCK attached to this node, in source order. PHP hands the last one to
     * `getDocComment()`; the ones above it are just comments, which is exactly how a second docblock
     * goes unnoticed.
     *
     * @return list<\PhpParser\Comment>
     */
    public function docblocks(): array
    {
        $blocks = [];

        foreach ($this->node?->getComments() ?? [] as $comment) {
            if (str_starts_with($comment->getText(), '/**')) {
                $blocks[] = $comment;
            }
        }

        return $blocks;
    }

    /**
     * Does this declaration carry MORE THAN ONE docblock — a stack where one block belongs?
     */
    public function hasStackedDocblocks(): bool
    {
        return count($this->docblocks()) > 1;
    }

    /**
     * May this stack be folded into ONE block without inventing documentation? Two things forbid it:
     *
     * - A block standing APART from the rest (a blank line between it and the next) is not a second
     *   description of this declaration — it is a block an insertion orphaned from the declaration it
     *   was written for, and folding it in attributes another thing's prose to this one (#415).
     * - Blocks whose tags would contradict each other once merged ({@see Docblock::foldable}, #417).
     *
     * The sin stands either way — the stack is still documentation nobody reads — but the fix is a
     * human's to make: no automatic rewrite can tell whose words those are.
     */
    public function docblockStackIsFoldable(): bool
    {
        $blocks = $this->docblocks();

        foreach ($blocks as $index => $block) {
            if ($index > 0 && $block->getStartLine() - $blocks[$index - 1]->getEndLine() > 1) {
                return false;
            }
        }

        return Docblock::foldable(array_map(static fn (Comment $c): string => $c->getText(), $blocks));
    }

    /**
     * Is this node's docblock written with a delimiter sharing a line with its text — a one-liner, or a
     * block that opens or closes next to content? {@see Docblock}
     */
    public function hasInlineDocblock(): bool
    {
        $doc = $this->node?->getDocComment();

        return $doc !== null && Docblock::isInline($doc->getText());
    }

    /**
     * Does any comment attached to this node hold commented-out PHP rather than prose? Dead code kept
     * in a comment is its own problem, and never prose to be read as documentation.
     * {@see \JesseGall\CodeCommandments\Ast\Support\CommentedCode}
     */
    public function hasCommentedOutCode(): bool
    {
        return array_any($this->lineComments(), static fn (string $c) => CommentedCode::isCode($c));
    }

    /**
     * Is this node an element of an array literal — an item in a list, rather than a statement?
     */
    public function isArrayItem(): bool
    {
        return $this->node instanceof ArrayItem || $this->parent()->node instanceof ArrayItem;
    }

    /**
     * Every word this statement's own HEAD spells — its identifiers, class names, string literals and
     * the plain-English name of the construct — stemmed for comparison. A nested body is not read.
     * {@see \JesseGall\CodeCommandments\Ast\Support\CodeWords}
     *
     * @return list<string>
     */
    public function codeWords(): array
    {
        return $this->node === null ? [] : CodeWords::of($this->node);
    }

    /**
     * A formatting-blind fingerprint of this whole function/method — its
     * signature, body, and modifiers serialised structurally so spacing, newlines,
     * and comments don't change it. Two declarations with the same hash are the
     * same code. Empty string when this isn't a function declaration.
     */
    public function structuralHash(): string
    {
        return $this->isFunctionDeclaration() ? StructuralHash::of($this->node) : '';
    }

    /**
     * Like {@see structuralHash} but fuzzier — variable names and scalar literals
     * are blanked, so two functions with the same SHAPE that differ only in their
     * variable names or constants (a near-duplicate / type-2 clone) hash the same.
     */
    public function shapeHash(): string
    {
        return $this->isFunctionDeclaration() ? StructuralHash::normalized($this->node) : '';
    }

    /**
     * How many AST nodes make up this function/method's body — a size proxy used
     * to ignore trivial declarations (one-line getters, empty stubs) that are
     * legitimately alike. Zero for a non-function or a bodyless declaration.
     */
    public function bodyNodeCount(): int
    {
        if (! $this->isFunctionDeclaration() || $this->node->stmts === null) {
            return 0;
        }

        return count((new NodeFinder)->find($this->node->stmts, static fn (Node $node) => true));
    }

    /**
     * Is this declaration a pure MANIFEST — a body that is nothing but `return [ … ];`, a single
     * return of an array literal? Such methods (`outputs()`, `rules()`, `casts()`, a config map)
     * DECLARE a data shape; they have no control-flow skeleton to parameterise, so two that merely
     * share that shape across independent classes are not a near-duplicate smell — factoring them
     * would couple unrelated declarations. False for anything carrying logic.
     */
    public function returnsArrayLiteralOnly(): bool
    {
        return $this->isFunctionDeclaration() && $this->soleArrayLiteralOutput() !== null;
    }

    /**
     * Is this declaration a constructor? A constructor is excluded from near-duplicate detection: that sin's
     * fix is "parameterise the shared shape into ONE method", but two DIFFERENT classes cannot share a
     * constructor — each type declares its own — so a similar `__construct` (assign the params, forward to
     * `parent`) is expected structure, not a redundant algorithm to hoist.
     */
    public function isConstructorDeclaration(): bool
    {
        return $this->node instanceof ClassMethod && strtolower($this->node->name->toString()) === '__construct';
    }

    /**
     * Is this declaration a NAMED CONSTRUCTOR of its OWN type — a factory whose body mints a fresh instance of
     * the enclosing class (`return new self(...)`, `new static(...)`, `Money::zero() => new Money(...)`)? Its
     * parameters are the object's own fields being BORN at the one build boundary — exactly like `__construct`,
     * not a loose data clump threaded between collaborators. Excluded from the data-clump smell for the same
     * reason {@see isConstructorDeclaration} is: the fix "hoist the clump into a value object" is already what
     * this method's return type IS.
     */
    public function isNamedConstructor(): bool
    {
        if (! $this->isFunctionDeclaration()) {
            return false;
        }

        $enclosing = $this->enclosingClassName();
        $short = $enclosing === null ? null : ltrim(strrchr('\\' . $enclosing, '\\'), '\\');

        return new NodeFinder()->findFirst(
            (array) ($this->node->stmts ?? []),
            static function (Node $node) use ($short): bool {
                if (! $node instanceof New_ || ! $node->class instanceof Name) {
                    return false;
                }

                $name = $node->class->toString();

                return in_array(strtolower($name), ['self', 'static'], true)
                    || ($short !== null && strcasecmp(ltrim(strrchr('\\' . $name, '\\'), '\\'), $short) === 0);
            },
        ) !== null;
    }

    /**
     * Is this function/method's body a SOLE expression return — exactly one statement, `return <expr>;`, with
     * no local variables, branches or loops? Such a body is a declarative descriptor or a one-line delegate
     * (`style() => new Style(...)`, `checksum() => $this->fingerprint(...)`), not a copy-pasted PROCEDURE: it
     * has no control-flow skeleton to hoist, so two that coincide across independent classes are incidental
     * likeness, not shared logic. Excluded from duplicate detection for the same reason a tiny getter is.
     */
    public function isSoleReturnExpression(): bool
    {
        if (! $this->isFunctionDeclaration()) {
            return false;
        }

        $stmts = $this->node->stmts ?? [];

        return count($stmts) === 1 && $stmts[0] instanceof Return_ && $stmts[0]->expr !== null;
    }

    /**
     * The VOID twin of {@see isSoleReturnExpression}: a body that is exactly one expression statement
     * and nothing else — `$this->database->statement(<<<'SQL' … SQL, [$a, $b, $c, $d]);`.
     *
     * Same reasoning, same verdict. One call is a named one-line delegate, not a copy-pasted
     * procedure: there is no control-flow skeleton to hoist, so what two of them share is the API
     * they both call, and what they DIFFER in is the data they hand it. Parameterising that would
     * collapse two intent-revealing steps behind a generic runner and relocate the data, not remove
     * a duplicate.
     */
    public function isSoleExpressionStatement(): bool
    {
        if (! $this->isFunctionDeclaration()) {
            return false;
        }

        $stmts = $this->node->stmts ?? [];

        return count($stmts) === 1 && $stmts[0] instanceof Expression;
    }

    /**
     * The `new self(...)`/`new static(...)` a WITHER re-threads its whole field list through — a body
     * that is one `return new self($this->a, $this->b, $changed, $this->d);`, carrying most fields
     * across untouched to alter one or two. Every new field must then be added to N of these, and
     * PHP 8.5's clone-with says the same thing in one line.
     *
     * Null unless the shape is unmistakable: a sole-return `new` of the OWN type, at least
     * {@see WITHER_CARRIED_FLOOR} arguments that are verbatim `$this->prop`, and at least one that
     * isn't (with no carried argument, it is a copy, not a wither).
     */
    public function handRolledWither(): ?New_
    {
        $new = $this->isSoleReturnExpression() ? ($this->node->stmts[0]->expr ?? null) : null;

        if (! $new instanceof New_ || ! $new->class instanceof Name || ! $this->namesOwnType($new->class)) {
            return null;
        }

        $carried = 0;
        $changed = 0;

        foreach ($new->args as $arg) {
            if (! $arg instanceof Arg || $arg->unpack) {
                return null;
            }

            if (new self($arg->value)->isOwnPropertyRead()) {
                $carried++;
            } else {
                $changed++;
            }
        }

        return $carried >= self::WITHER_CARRIED_FLOOR && $changed >= 1 && $this->constructorIsPromotionOnly() ? $new : null;
    }

    /**
     * Is this expression's RESULT thrown away — it is the whole of an expression statement, so
     * nothing reads what it returns? The structural difference between a WRITE and a READ: a
     * `Config::set('k', $v);` stands alone, while every read is assigned, returned, or passed on.
     */
    public function resultIsDiscarded(): bool
    {
        $parent = $this->node?->getAttribute('parent');

        return $parent instanceof Expression && $parent->expr === $this->node;
    }

    /**
     * Is THIS `new` the rebuild a hand-rolled wither performs — i.e. is it the construction
     * {@see handRolledWither} identifies on its enclosing method? Read on a `whereNew()` node, so the
     * sin reports (and `repent` replaces) the construction itself rather than the whole method.
     */
    public function isWitherRebuild(): bool
    {
        $function = $this->enclosingFunction();

        return $function !== null && new self($function)->handRolledWither() === $this->node;
    }

    /**
     * Is this expression a plain `$this->prop` read — a field carried across verbatim, as opposed to a
     * value computed or passed in? The primitive behind "which arguments ride along unchanged".
     */
    public function isOwnPropertyRead(): bool
    {
        return $this->node instanceof PropertyFetch
            && $this->node->var instanceof Variable
            && $this->node->var->name === 'this'
            && $this->node->name instanceof Identifier;
    }

    /**
     * Is this the empty string literal — the absence of a value written where one is promised?
     */
    public function isEmptyString(): bool
    {
        return $this->node instanceof String_ && $this->node->value === '';
    }

    /**
     * This call's argument at $index as a STRING LITERAL, or null when it isn't one (or isn't there).
     * The one home for "the literal key/name this call names" — a config key, a route name, an event id.
     */
    public function stringArgument(int $index = 0): ?string
    {
        $value = $this->arguments()[$index]->value ?? null;

        return $value instanceof String_ ? $value->value : null;
    }

    /**
     * This call's argument at $index as the FQCN of a `Foo::class` fetch, or null. The class-literal
     * counterpart of {@see stringArgument} — how a registration names the type it wires.
     */
    public function classArgument(int $index = 0): ?string
    {
        $value = $this->arguments()[$index]->value ?? null;

        return $value instanceof ClassConstFetch
            && $value->class instanceof Name
            && $value->name instanceof Identifier
            && $value->name->toString() === 'class'
                ? ltrim($value->class->toString(), '\\')
                : null;
    }

    /**
     * How many arguments this call passes — 0 for a node that isn't a call.
     */
    public function argumentCount(): int
    {
        return count($this->arguments());
    }

    /**
     * The constructor PARAMETER an argument targets — matched by NAME when the argument is named, by
     * POSITION otherwise. Null when nothing matches, so a caller never has to guess which slot it hit.
     *
     * @param  list<Param>  $params
     */
    public static function paramForArgument(array $params, Arg $argument, int $index): ?Param
    {
        if ($argument->name === null) {
            return $params[$index] ?? null;
        }

        foreach ($params as $param) {
            if (($param->var->name ?? null) === $argument->name->toString()) {
                return $param;
            }
        }

        return null;
    }

    /**
     * The PROPERTY a positional argument at $index writes — the constructor parameter at that
     * position, but only when it is promoted and non-variadic (so its name IS the property name).
     * Null for anything else, because a key that had to be guessed is worse than no rewrite at all.
     *
     * Shared by every scribe that turns positional arguments into a keyed array ({@see
     * \JesseGall\CodeCommandments\Scribes\Backend\NewDataObjectScribe}, {@see
     * \JesseGall\CodeCommandments\Scribes\Backend\HandRolledWitherScribe}).
     *
     * @param  list<Param>  $params
     */
    public static function promotedParamName(array $params, int $index): ?string
    {
        $param = $params[$index] ?? null;

        if ($param === null || $param->flags === 0 || $param->variadic || ! $param->var instanceof Variable || ! is_string($param->var->name)) {
            return null;
        }

        return $param->var->name;
    }

    /**
     * Is the enclosing class's constructor PURE PROMOTION — every parameter promoted, and no body?
     *
     * The gate that makes clone-with a faithful replacement rather than a behaviour change: `new self(…)`
     * RUNS the constructor, `clone($this, […])` does not. A constructor that validates, normalises or
     * derives a field would be silently skipped, so a wither on such a class is left alone.
     */
    public function constructorIsPromotionOnly(): bool
    {
        $constructor = $this->enclosingClass()?->getMethod('__construct');

        if ($constructor === null || $constructor->params === []) {
            return false;
        }

        return ($constructor->stmts ?? []) === []
            && array_all($constructor->params, static fn (Param $param): bool => $param->flags !== 0 && ! $param->variadic);
    }


    /**
     * Does this class name refer to the ENCLOSING type — `self`, `static`, or its own name?
     */
    private function namesOwnType(Name $name): bool
    {
        $spelled = strtolower($name->toString());

        if (in_array($spelled, ['self', 'static'], true)) {
            return true;
        }

        $enclosing = $this->enclosingClassName();

        return $enclosing !== null && strtolower(ltrim($name->toString(), '\\')) === strtolower($enclosing);
    }

    /**
     * Does this node sit inside a NAMED CONSTRUCTOR of the enclosing type — a factory whose body mints a
     * fresh instance of its own class ({@see isNamedConstructor})? The HYDRATION boundary: the one place a
     * raw payload legitimately becomes typed. Sibling of {@see NodeMatch::isWithinSerializationBoundary},
     * which says the same thing for the hooks the LANGUAGE dictates.
     */
    public function isWithinNamedConstructor(): bool
    {
        $function = $this->enclosingFunction();

        return $function !== null && new self($function)->isNamedConstructor();
    }

    /**
     * Does this declaration carry an `@deprecated` docblock tag? Deprecated code is a frozen snapshot on its
     * way out — you never hoist LIVE logic toward it and you don't refactor code that's slated for deletion —
     * so a duplicate/near-duplicate that involves a deprecated declaration is not a smell to act on. Excluded
     * from duplicate detection: the semantic `@deprecated` marker, not a folder-name convention.
     */
    public function isDeprecated(): bool
    {
        $doc = $this->node?->getDocComment()?->getText();

        return $doc !== null && str_contains($doc, '@deprecated');
    }

    /**
     * Is this a resolve-or-throw ACCESSOR — one or more null-guard `if (…) { throw … }` statements followed
     * by a single `return $this->prop` (or a local)? That is a language IDIOM for exposing a guarded optional,
     * not copy-pasted logic: two of them in independent classes are incidentally alike (they differ only in
     * the exception thrown), and hoisting them would COUPLE the classes. Excluded from duplicate detection
     * for the same reason as {@see returnsArrayLiteralOnly} — a shared shape across unrelated types isn't a
     * near-duplicate smell.
     */
    public function isGuardedAccessor(): bool
    {
        if (! $this->node instanceof ClassMethod) {
            return false;
        }

        $stmts = $this->node->stmts ?? [];
        $last = $stmts === [] ? null : end($stmts);

        if (! $last instanceof Return_ || ! ($last->expr instanceof PropertyFetch || $last->expr instanceof Variable)) {
            return false;
        }

        foreach (array_slice($stmts, 0, count($stmts) - 1) as $guard) {
            if (! self::isThrowingGuard($guard)) {
                return false;
            }
        }

        return count($stmts) >= 2; // at least one guard + the return
    }

    /**
     * An `if (…) { throw … }` with no else — a pure bail-out guard.
     */
    private static function isThrowingGuard(Node $stmt): bool
    {
        if (! $stmt instanceof If_ || $stmt->else !== null || $stmt->elseifs !== [] || $stmt->stmts === []) {
            return false;
        }

        foreach ($stmt->stmts as $inner) {
            if (! ($inner instanceof Expression && $inner->expr instanceof Throw_)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Does this subtree reference the instance or its own class behaviour — a `$this`
     * variable (property read, method call, closure capture), or a `self::`/`static::`/
     * `parent::` CALL or static-property read (own, late-bound behaviour — the value
     * derives from which class you are, exactly like `$this->…`)? The "is this computed
     * FROM the object" question. A class CONSTANT (`self::X`, an enum case) does NOT
     * count: constants are valid property defaults, so a body made only of them still
     * produces the same value however the object is configured.
     */
    public function referencesThis(): bool
    {
        return new NodeFinder()->findFirst(
            [$this->node],
            static fn (Node $node): bool => ($node instanceof Variable && $node->name === 'this')
                || (($node instanceof StaticCall || $node instanceof StaticPropertyFetch)
                    && $node->class instanceof Name
                    && in_array($node->class->toLowerString(), ['self', 'static', 'parent'], true)),
        ) !== null;
    }

    /**
     * Is this a property hook DECLARATION without a body — an interface's / abstract
     * class's `{ get; }`, a requirement rather than an implementation?
     */
    public function isAbstractHook(): bool
    {
        return $this->node instanceof PropertyHook && $this->node->body === null;
    }

    /**
     * Does the property this hook belongs to ALSO declare a `set` hook? A get/set pair
     * is a property that earns its hook syntax as a unit — judged together, not by the
     * getter alone.
     */
    public function hookedPropertyHasSetter(): bool
    {
        $property = $this->parent()->node;

        $hooks = match (true) {
            $property instanceof Property => $property->hooks,
            $property instanceof Param => $property->hooks,
            default => [],
        };

        return array_any($hooks, static fn (PropertyHook $hook): bool => $hook->name->toString() === 'set');
    }

    /**
     * The single array literal this member's body EVALUATES TO, or null. Covers a computed slot's getter
     * (`get => [ … ]`, `get { return [ … ]; }`) and a function/method that is nothing but `return [ … ];`.
     */
    public function soleArrayLiteralOutput(): ?Array_
    {
        if ($this->node instanceof PropertyHook) {
            return $this->node->body instanceof Array_
                ? $this->node->body
                : self::soleReturnedArray($this->node->body);
        }

        return $this->isFunctionDeclaration() ? self::soleReturnedArray($this->node->stmts) : null;
    }

    /**
     * The array literal of a body that is exactly `return [ … ];` — a one-statement block whose only
     * statement returns an array literal. Null for anything else (no body, more statements, logic).
     */
    private static function soleReturnedArray(mixed $stmts): ?Array_
    {
        if (! is_array($stmts) || count($stmts) !== 1 || ! $stmts[0] instanceof Return_) {
            return null;
        }

        return $stmts[0]->expr instanceof Array_ ? $stmts[0]->expr : null;
    }

    /**
     * Does this method SAVE one of its own properties to a local and later RESTORE
     * it from that local — `$prev = $this->scope; … $this->scope = $prev;`? That
     * dance only makes sense when the field is per-call scratch state mutated for
     * the duration of the call; the data is really an input that should be passed.
     */
    public function hasOwnStateSaveAndRestore(): bool
    {
        if (! $this->isFunctionDeclaration() || $this->node->stmts === null) {
            return false;
        }

        // Dynamic scope, not scratch state: a method that brackets a callable it was
        // HANDED (`bind($target, $callback)`, `withinMutation(…, $callback)`) sets the
        // field for the duration of code it doesn't own — you can't thread a parameter
        // into a closure's transitive callees. That's the Context pattern, not a lie.
        if ($this->hasCallableParam()) {
            return false;
        }

        $savedInto = [];      // property name => local var it was saved into
        $restoredFrom = [];   // property name => list of local vars written back

        foreach ((new NodeFinder)->findInstanceOf($this->node->stmts, Assign::class) as $assign) {
            $target = self::selfPropertyOf($assign->var);
            $source = self::selfPropertyOf($assign->expr);

            if ($source !== null && AstNode::variableNameOf($assign->var) !== null) {
                $savedInto[$source] = $assign->var->name;
            }

            if ($target !== null && AstNode::variableNameOf($assign->expr) !== null) {
                $restoredFrom[$target][] = $assign->expr->name;
            }
        }

        foreach ($savedInto as $property => $local) {
            if (in_array($local, $restoredFrom[$property] ?? [], true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Does this function declare a parameter typed `callable` or `Closure`? Such a
     * method runs code it was handed — the hallmark of the dynamic-scope pattern.
     */
    protected function hasCallableParam(): bool
    {
        foreach ($this->node->params as $param) {
            foreach (self::typeNames($param->type) as $name) {
                if ($name === 'callable' || $name === 'Closure') {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * The simple names inside a (possibly nullable/union/intersection) type hint.
     *
     * @return list<string>
     */
    protected static function typeNames(?Node $type): array
    {
        if ($type instanceof NullableType) {
            return self::typeNames($type->type);
        }

        if ($type instanceof UnionType || $type instanceof IntersectionType) {
            return array_merge(...array_map(self::typeNames(...), $type->types));
        }

        if ($type instanceof Identifier) {
            return [$type->toString()];
        }

        if ($type instanceof Name) {
            return [$type->getLast()];
        }

        return [];
    }


    /**
     * Is this a function/method declared to return a nullable CLASS (`?C` /
     * `C | null`) — a finder that resolves to a value-or-null?
     */
    public function returnsNullableObject(): bool
    {
        return $this->isFunctionDeclaration()
            && TypeName::nullableClass($this->node->returnType) !== null;
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
     * The fully-qualified types this `catch` clause names — one entry per alternative, so
     * `catch (BreakSignal | StopSignal)` yields both. Empty for any node that isn't a catch.
     *
     * @return list<string>
     */
    public function caughtTypes(): array
    {
        if (! $this->node instanceof Catch_) {
            return [];
        }

        return array_map(static fn (Name $type): string => $type->toString(), $this->node->types);
    }

    /**
     * Is this a `match` OVER A SUBJECT whose `default` arm returns an absence value
     * (`null`/`false`/`[]`) instead of throwing? An unhandled case silently
     * swallowed — a missing case is a bug, and the default should say so.
     *
     * A `match (true)`/`match (false)` is excluded: that is boolean-condition dispatch
     * (if/elseif sugar over arbitrary, often open predicates like `instanceof`), NOT a
     * closed value set, so its `default` is a normal `else` branch — there is no missing
     * case to throw on. The sin is a hole in a CLOSED set; a boolean dispatch has none.
     */
    public function isMatchWithAbsenceDefault(): bool
    {
        if (! $this->node instanceof Match_ || $this->matchesOnBooleanLiteral()) {
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
     * Is the match subject a bare boolean literal (`match (true)`/`match (false)`) — the
     * condition-dispatch form, not a match over a value/enum.
     */
    private function matchesOnBooleanLiteral(): bool
    {
        return $this->node instanceof Match_
            && $this->node->cond instanceof ConstFetch
            && in_array($this->node->cond->name->toLowerString(), ['true', 'false'], true);
    }

    /**
     * Is this an absence value — `null`, `false`, an empty array, or nothing?
     */
    public function isAbsenceValue(): bool
    {
        return $this->node === null
            || $this->isNull()
            || ($this->node instanceof ConstFetch && $this->node->name->toLowerString() === 'false')
            || $this->isEmptyArrayLiteral();
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
            if ($variable->name !== $catch->var->name) {
                continue;
            }

            return false; // the caught exception is passed on
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
     * Does this class-like declaration carry a `@method` docblock tag whose method
     * name matches a method it ACTUALLY declares? That re-declares a visible method
     * the IDE already sees — "Method with same name already defined in this class".
     * `@method` exists to describe the *invisible* magic overloads (Spatie's
     * `::from()`/`::collect()`), so a tag naming a concrete method is always a bug.
     */
    public function docblockMethodTagRedeclaresRealMethod(): bool
    {
        if (! $this->node instanceof ClassLike) {
            return false;
        }

        $doc = $this->node->getDocComment();

        if ($doc === null) {
            return false;
        }

        $declared = [];

        foreach ($this->node->getMethods() as $method) {
            $declared[strtolower($method->name->toString())] = true;
        }

        foreach (self::methodTagNames($doc->getText()) as $name) {
            if (isset($declared[strtolower($name)])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Does the function enclosing this node declare a SHAPED-array return — a
     * `@return array{field: T, …}` sealed struct with named keys? Such a return is a typed,
     * statically-checkable record contract (PHPStan/Psalm enforce the shape), not a loose
     * `array<string, mixed>` bag — so an array-bag rule should leave it alone. A positional
     * `array{0: T, 1: T}` tuple (numeric keys) is NOT a struct and doesn't count.
     */
    public function enclosingFunctionReturnsShapedArray(): bool
    {
        $doc = $this->enclosingFunction()?->getDocComment()?->getText();

        return $doc !== null && self::declaresShapedArrayReturn($doc);
    }

    /**
     * Is there a `@return array{…}` with at least one NAMED (non-numeric) key in $docblock?
     */
    private static function declaresShapedArrayReturn(string $docblock): bool
    {
        foreach (preg_split('/\R/', $docblock) ?: [] as $line) {
            if (preg_match('/@return\s+\??array\s*\{/', $line) !== 1) {
                continue;
            }

            if (preg_match('/\{[^}]*[A-Za-z_]\w*\??\s*:/', $line) === 1) {
                return true; // a named field key — a struct, not a positional tuple or loose map
            }
        }

        return false;
    }

    /**
     * Does the function enclosing this node declare a SEQUENCE return — `@return list<T>`,
     * `@return array<int, T>` or `@return T[]`? All three say the same thing: N of one kind, where
     * order is the meaning and no position carries a name. That is the OPPOSITE of a tuple, whose
     * every slot means something different, and it is statically checked (PHPStan/Psalm), so a
     * tuple rule must take the author's declared type at its word.
     */
    public function enclosingFunctionReturnsSequence(): bool
    {
        $doc = $this->enclosingFunction()?->getDocComment()?->getText();

        return $doc !== null && self::declaresSequenceReturn($doc);
    }

    /**
     * Is there a `@return` naming a homogeneous sequence — `list<…>`, `array<int, …>` or `T[]` —
     * in $docblock?
     */
    private static function declaresSequenceReturn(string $docblock): bool
    {
        foreach (preg_split('/\R/', $docblock) ?: [] as $line) {
            if (preg_match('/@return\s+\??(non-empty-)?list\s*</', $line) === 1) {
                return true;
            }

            if (preg_match('/@return\s+\??array\s*<\s*int\s*,/', $line) === 1) {
                return true;
            }

            if (preg_match('/@return\s+\??[\\\\\w|]+\[\]/', $line) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * The method names declared by `@method` tags in a docblock. The name is the
     * identifier immediately before the parameter list, taken as the LAST `word(`
     * on the line so a conditional return type's own parens
     * (`@method ($x is A ? B : C) collect(...)`) don't fool it.
     *
     * @return list<string>
     */
    protected static function methodTagNames(string $docblock): array
    {
        $names = [];

        foreach (preg_split('/\R/', $docblock) ?: [] as $line) {
            if (preg_match('/^\s*\*?\s*@method\b/', $line) !== 1) {
                continue;
            }

            if (preg_match_all('/(\w+)\s*\(/', $line, $matches) >= 1) {
                $names[] = (string) end($matches[1]);
            }
        }

        return $names;
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
        if (! $this->isFunctionDeclaration()) {
            return false;
        }

        $doc = $this->node->getDocComment();

        if ($doc === null) {
            return false;
        }

        $nativeTypes = [];

        foreach ($this->node->params as $param) {
            $type = self::typeToString($param->type);

            if ($type !== null && AstNode::variableNameOf($param->var) !== null) {
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
        foreach (self::ancestorsWithinFunction($this->node) as $ancestor) {
            if ($ancestor instanceof Ternary) {
                return false;
            }
        }

        foreach ([$this->node->if, $this->node->else] as $branch) {
            if ($branch instanceof Node && (new NodeFinder)->findInstanceOf($branch, Ternary::class) !== []) {
                return true;
            }
        }

        return false;
    }

    /**
     * The EXACT structural fingerprint of this node's subtree — identifiers, class names and values included
     * (unlike {@see structuralHash}, which is function-only). Two occurrences hash equal iff they are the
     * same code up to formatting. The reusable substrate for "this expression recurs verbatim" detection.
     */
    public function exactHash(): string
    {
        return $this->node === null ? '' : StructuralHash::of($this->node);
    }

    /**
     * Is this the OUTERMOST `&&` chain that narrows a value through ≥2 `instanceof` checks —
     * `$x instanceof A && $x->y instanceof B && $x->y->z instanceof C`? A recurring one is a type guard with
     * no name; the fix is a named predicate. Only the chain root is flagged, so one guard yields one finding.
     */
    public function isTypeNarrowingGuard(): bool
    {
        return $this->node instanceof BooleanAnd
            && ! $this->node->getAttribute('parent') instanceof BooleanAnd
            && self::instanceofConjuncts($this->node) >= 2;
    }

    /**
     * How many conjuncts of an `&&` chain are `instanceof` checks.
     */
    private static function instanceofConjuncts(Node $node): int
    {
        if ($node instanceof BooleanAnd) {
            return self::instanceofConjuncts($node->left) + self::instanceofConjuncts($node->right);
        }

        return $node instanceof Instanceof_ ? 1 : 0;
    }

    /**
     * A canonical fingerprint of an `&&` guard that ignores BOTH conjunct ORDER and local-variable ALIASING —
     * `$obj->some && $other->some` fingerprints the same as `$other->some && $obj->some` and as
     * `$objSome && $otherSome` (with `$objSome = $obj->some`). So the same check, however spelled or ordered,
     * buckets together. Composes {@see StructuralHash::canonical}; empty for a non-`&&` node.
     */
    public function canonicalGuardHash(): string
    {
        if (! $this->node instanceof BooleanAnd) {
            return '';
        }

        $aliases = $this->localAliases();
        $hashes = array_map(static fn (Node $conjunct) => StructuralHash::canonical($conjunct, $aliases), self::flattenConjuncts($this->node));
        sort($hashes);

        return sha1(implode('|', $hashes));
    }

    /**
     * Is this the OUTERMOST `&&` guard that is SUBSTANTIVE and not pure-type — ≥2 conjuncts, ≥1 of them NOT a
     * bare `instanceof` (a pure-instanceof chain is {@see isTypeNarrowingGuard}'s domain), and ≥2 "substance
     * units" (a property/method reach — counted THROUGH aliases, so `$objSome` counts as `$obj->some`). This
     * keeps trivial `$a && $b` out. A recurring one wants a named predicate.
     */
    public function isSubstantiveGuard(): bool
    {
        if (! $this->node instanceof BooleanAnd || $this->node->getAttribute('parent') instanceof BooleanAnd) {
            return false;
        }

        $conjuncts = self::flattenConjuncts($this->node);

        if (count($conjuncts) < 2 || self::countMatching($conjuncts, static fn (Node $c): bool => ! $c instanceof Instanceof_) === 0) {
            return false;
        }

        $aliases = $this->localAliases();
        $substance = 0;

        foreach ($conjuncts as $conjunct) {
            $target = AstNode::variableNameOf($conjunct) !== null && isset($aliases[$conjunct->name])
                ? $aliases[$conjunct->name]
                : $conjunct;
            $substance += self::reachCount($target);
        }

        return $substance >= 2;
    }

    /**
     * Single-assignment locals of the enclosing function — name → the expression assigned — the aliases a
     * guard's fingerprint sees through. Only-once-assigned so a reassigned local never resolves ambiguously.
     *
     * @return array<string, Node>
     */
    private function localAliases(): array
    {
        $function = $this->enclosingFunction();

        if ($function === null) {
            return [];
        }

        $counts = [];
        $rhs = [];

        foreach ((new NodeFinder)->findInstanceOf($function, Assign::class) as $assign) {
            if (! (AstNode::variableNameOf($assign->var) !== null)) {
                continue;
            }

            $counts[$assign->var->name] = ($counts[$assign->var->name] ?? 0) + 1;
            $rhs[$assign->var->name] = $assign->expr;
        }

        return array_filter($rhs, static fn (Node $expr, string $name): bool => $counts[$name] === 1, ARRAY_FILTER_USE_BOTH);
    }

    /**
     * @return list<Node>
     */
    private static function flattenConjuncts(Node $node): array
    {
        return $node instanceof BooleanAnd
            ? [...self::flattenConjuncts($node->left), ...self::flattenConjuncts($node->right)]
            : [$node];
    }

    /**
     * How many property/method reaches a node's subtree contains — its "substance".
     */
    private static function reachCount(Node $node): int
    {
        return count((new NodeFinder)->find($node, static fn (Node $n): bool =>
            $n instanceof PropertyFetch || $n instanceof MethodCall || $n instanceof StaticCall || $n instanceof Instanceof_));
    }

    /**
     * @param  list<Node>  $nodes
     * @param  callable(Node): bool  $test
     */
    private static function countMatching(array $nodes, callable $test): int
    {
        return count(array_filter($nodes, $test));
    }

    /**
     * Is this a CONDITIONAL ARRAY-ELEMENT spread — `...($x !== null ? ['k' => $x] : [])` inside an array
     * literal, or `array_merge($base, $cond ? ['k' => $v] : [])`? The tell is a ternary whose ONE branch is a
     * non-empty array literal and the OTHER an empty `[]` ("include these keys, else nothing"), used as a
     * spread element or an `array_merge` argument — the noise a null-dropping variadic factory replaces.
     */
    public function isConditionalArraySpread(): bool
    {
        if (! $this->node instanceof Ternary || ! $this->node->if instanceof Node) {
            return false;
        }

        if (! self::isOneEmptyOneFilledArray($this->node->if, $this->node->else)) {
            return false;
        }

        $parent = $this->node->getAttribute('parent');

        return self::isSpreadItemOf($parent, $this->node) || self::isArrayMergeArgumentOf($parent, $this->node);
    }

    /**
     * Are the two nodes both array literals, exactly one of them empty (`['k'=>$v]` vs `[]`)?
     */
    private static function isOneEmptyOneFilledArray(Node $a, Node $b): bool
    {
        return $a instanceof Array_ && $b instanceof Array_ && (($a->items === []) xor ($b->items === []));
    }

    /**
     * Is $parent the spread item `...$ternary` wrapping this ternary?
     */
    private static function isSpreadItemOf(mixed $parent, Node $ternary): bool
    {
        return $parent instanceof ArrayItem && $parent->unpack && $parent->value === $ternary;
    }

    /**
     * Is $parent an `array_merge(..., $ternary)` argument wrapping this ternary?
     */
    private static function isArrayMergeArgumentOf(mixed $parent, Node $ternary): bool
    {
        if (! $parent instanceof Arg || $parent->value !== $ternary) {
            return false;
        }

        $call = $parent->getAttribute('parent');

        return $call instanceof FuncCall && $call->name instanceof Name && strtolower($call->name->toString()) === 'array_merge';
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

        return self::isBailOut(end($statements));
    }

    /**
     * Does $statement leave the block for good — a `return`, a `throw`, a `continue` or a `break`?
     * The tail test behind both "is this a guard clause" and "is that else redundant": if the body
     * cannot fall through, whatever follows it is not an alternative.
     */
    public static function isBailOut(mixed $statement): bool
    {
        return $statement instanceof Return_
            || $statement instanceof Continue_
            || $statement instanceof Break_
            || ($statement instanceof Expression && $statement->expr instanceof Throw_);
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

    /**
     * Is this call `array_map`'s per-item callback — either inside the closure/arrow
     * fn passed to it (`array_map(fn ($r) => X::from($r), $rows)`) or passed as a
     * first-class callable (`array_map(X::from(...), $rows)`)? `array_map` over a
     * list is the same item-by-item mapping a loop is — Spatie's `::collect()` does
     * it in one pass.
     */
    public function isWithinArrayMap(): bool
    {
        $closure = $this->walkUp(static fn (Node $node): bool => $node instanceof Closure || $node instanceof ArrowFunction);

        if ($closure !== null && self::isArrayMapArgument($closure->getAttribute('parent'))) {
            return true;
        }

        return self::isArrayMapArgument($this->node?->getAttribute('parent'));
    }


    /**
     * Walk up from $node (exclusive) testing each ancestor.
     */
    protected static function within(Node $node, callable $test): bool
    {
        $current = $node->getAttribute('parent');

        while ($current instanceof Node) {
            if ($test($current)) {
                return true;
            }

            $current = $current->getAttribute('parent');
        }

        return false;
    }

    /**
     * Is $node an argument to an `array_map(...)` call?
     */
    protected static function isArrayMapArgument(?Node $node): bool
    {
        if (! $node instanceof Arg) {
            return false;
        }

        $call = $node->getAttribute('parent');

        return $call instanceof FuncCall
            && $call->name instanceof Name
            && $call->name->toString() === 'array_map';
    }

    /**
     * Does this DECLARATION (method, property, param, class, or hook) carry an attribute whose SHORT name
     * matches one given — `#[Computed]`, `#[Hidden]`, …? (The per-field reader is {@see ClassField::hasAttribute}.)
     */
    public function hasAttribute(string ...$shortNames): bool
    {
        if ($this->node === null || ! property_exists($this->node, 'attrGroups')) {
            return false;
        }

        foreach ($this->node->attrGroups as $group) {
            foreach ($group->attrs as $attribute) {
                if (in_array(ClassName::short($attribute->name->toString()), $shortNames, true)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Does this field DECLARATION affirm null as part of its SERIALIZED wire contract — a wire-type
     * override attribute whose declared type is nullable, e.g. `#[LiteralTypeScriptType('Foo | null')]`?
     * Then the null is intentional (the author stated it on the wire), not a phantom nullable to tighten
     * away. Reads the attribute off the field's own {@see Param}/{@see Property} (walking up from a
     * declared item), so it works for a promoted param and a declared property alike.
     */
    public function declaresNullableWireType(): bool
    {
        $carrier = $this->node instanceof Param || $this->node instanceof Property
            ? $this->node
            : $this->walkUp(static fn (Node $node): bool => $node instanceof Param || $node instanceof Property);

        if (! $carrier instanceof Param && ! $carrier instanceof Property) {
            return false;
        }

        foreach ($carrier->attrGroups as $group) {
            foreach ($group->attrs as $attribute) {
                if (! in_array(ClassName::short($attribute->name->toString()), self::WIRE_TYPE_ATTRIBUTES, true)) {
                    continue;
                }

                $argument = $attribute->args[0]->value ?? null;

                if ($argument instanceof String_ && self::typeStringIsNullable($argument->value)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Does a TS type string declare null — a `?Foo` prefix, or a `null` member of a `|` union?
     */
    private static function typeStringIsNullable(string $type): bool
    {
        if (str_starts_with(trim($type), '?')) {
            return true;
        }

        foreach (explode('|', $type) as $part) {
            if (strtolower(trim($part)) === 'null') {
                return true;
            }
        }

        return false;
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
            if (! ($item instanceof ArrayItem)) {
                continue;
            }

            $literal = self::scalarLiteral($item->value);

            if ($literal !== null) {
                $literals[] = $literal;
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
    protected function flattenOr(BooleanOr $node): array
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

    protected static function comparedConstClass(Node $operand): ?string
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
     * Does this function/method take a nullable callback defaulting to `null`
     * (`?callable $cb = null`, `Closure|null $cb = null`) that the body then
     * null-normalises — a null-check, `??`, or truthiness guard before calling
     * it? That branch is a Null Object asking to be the default: pass a no-op
     * callable so the call site is unconditional.
     */
    public function hasNullNormalisedNullableCallback(): bool
    {
        if (! $this->isFunctionDeclaration()) {
            return false;
        }

        foreach ($this->node->params as $param) {
            if (! self::isNullableCallbackWithNullDefault($param) || ! $param->var instanceof Variable || ! is_string($param->var->name)) {
                continue;
            }

            // The guard must exist to avoid CALLING a null callback — so the param
            // is both null-checked and invoked. That is exactly the case a no-op
            // Null Object default dissolves; a stored/forwarded callback is not.
            if ($this->normalisesNullFor($param->var->name) && $this->isInvoked($param->var->name)) {
                return true;
            }
        }

        return false;
    }

    protected function isInvoked(string $paramName): bool
    {
        foreach ((new NodeFinder)->findInstanceOf($this->node, FuncCall::class) as $call) {
            // Called directly (`$cb(…)`) or via a coalesced default (`($cb ?? …)(…)`).
            $target = $call->name instanceof Coalesce ? $call->name->left : $call->name;

            if ($target instanceof Variable && $target->name === $paramName) {
                return true;
            }
        }

        return false;
    }

    protected static function isNullableCallbackWithNullDefault(Param $param): bool
    {
        $isNull = AstNode::isNullConstant($param->default);

        if (! $isNull) {
            return false;
        }

        // Both spellings of a nullable callback: `?callable` (NullableType) and
        // `callable|null` (UnionType with a null member).
        $candidates = match (true) {
            $param->type instanceof NullableType => [$param->type->type],
            $param->type instanceof UnionType => $param->type->types,
            default => [],
        };

        foreach ($candidates as $candidate) {
            $name = $candidate instanceof Identifier || $candidate instanceof Name ? $candidate->toString() : '';

            if (in_array(strtolower(ClassName::short($name)), ['callable', 'closure'], true)) {
                return true;
            }
        }

        return false;
    }

    protected function normalisesNullFor(string $paramName): bool
    {
        foreach ((new NodeFinder)->findInstanceOf($this->node, Variable::class) as $variable) {
            if ($variable->name !== $paramName) {
                continue;
            }

            $parent = $variable->getAttribute('parent');

            $isNullGuard = ($parent instanceof Identical || $parent instanceof NotIdentical)
                || ($parent instanceof Coalesce && $parent->left === $variable)
                || $parent instanceof BooleanNot
                || $parent instanceof BooleanAnd
                || $parent instanceof BooleanOr
                || self::isConditionOf($parent, $variable);

            if ($isNullGuard) {
                return true;
            }
        }

        return false;
    }

    /**
     * Does this declaration's whole body switch on one of its OWN `bool` parameters? The pairing of
     * {@see paramTypeNames} and {@see bodyIsTwoWayBranchOn}: a flag comes in, and the method is
     * nothing but the choice it makes.
     */
    public function switchesEntirelyOnABoolParam(): bool
    {
        foreach ($this->paramTypeNames() as $name => $type) {
            if (strtolower((string) $type) === 'bool' && $this->bodyIsTwoWayBranchOn($name)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Is this declaration's ENTIRE body one two-way branch on the variable $name — an `if ($flag)
     * {…} else {…}` with nothing around it, or a `match ($flag)` with a true arm and a false arm?
     * Then the parameter is not something the method works WITH; it selects which of two methods
     * the caller actually wanted. A branch with anything before or after it fails: that is a method
     * doing its job with a conditional in it, not a method that IS the conditional.
     */
    public function bodyIsTwoWayBranchOn(string $name): bool
    {
        if (! $this->isFunctionDeclaration() || count($this->node->stmts ?? []) !== 1) {
            return false;
        }

        $only = $this->node->stmts[0];

        if ($only instanceof If_) {
            return $only->elseifs === []
                && $only->else !== null
                && self::armDoesWork($only->stmts)
                && self::armDoesWork($only->else->stmts)
                && self::testsVariable($only->cond, $name);
        }

        $expression = match (true) {
            $only instanceof Return_ => $only->expr,
            $only instanceof Expression => $only->expr,
            default => null,
        };

        return $expression instanceof Match_
            && count($expression->arms) === 2
            && array_all($expression->arms, static fn (MatchArm $arm): bool => ! self::isBareValue($arm->body))
            && self::testsVariable($expression->cond, $name);
    }

    /**
     * Does this branch arm DO something, rather than just hand back a constant? Two arms that only
     * return different literals are a mapping from a bool to a value — a lookup, not two
     * behaviours — and an arm that exits with a bare constant is a guard answering a precondition.
     * Either way there are no two methods hiding in there to split apart.
     *
     * @param  list<Node\Stmt>  $statements
     */
    private static function armDoesWork(array $statements): bool
    {
        if (count($statements) !== 1) {
            return $statements !== [];
        }

        $only = $statements[0];

        return ! ($only instanceof Return_ && self::isBareValue($only->expr));
    }

    /**
     * Is this expression a constant sitting there — a literal, `null`/`true`, a class constant, or
     * nothing at all? The tell that an arm carries a VALUE rather than work.
     */
    private static function isBareValue(?Node $expression): bool
    {
        return $expression === null
            || $expression instanceof Scalar
            || $expression instanceof ConstFetch
            || $expression instanceof ClassConstFetch;
    }

    /**
     * Is this condition the variable $name itself, or its plain negation? Anything richer — a
     * comparison, a call, the flag combined with something else — is the method reasoning, not
     * merely obeying.
     */
    private static function testsVariable(Node $condition, string $name): bool
    {
        if ($condition instanceof BooleanNot) {
            $condition = $condition->expr;
        }

        return $condition instanceof Variable && $condition->name === $name;
    }

    /**
     * Is this a method/function whose parameters are ALL `bool` — at least one — and which branches
     * on every one of them? A pure CHOOSER: its whole answer is decided by flags handed in, so the
     * signature names none of the subject those flags describe. A constructor is excluded (its
     * params are the object's own fields being born), and so is a body that merely stores or
     * forwards a flag rather than deciding with it.
     */
    public function decidesOnBoolsAlone(): bool
    {
        if (! $this->isFunctionDeclaration() || $this->isConstructorDeclaration()) {
            return false;
        }

        $types = $this->paramTypeNames();

        if ($types === []) {
            return false;
        }

        foreach ($types as $name => $type) {
            if (strtolower((string) $type) !== 'bool' || ! $this->readsAsCondition($name)) {
                return false;
            }
        }

        return true;
    }

    /**
     * This function/method's parameters as `name => written type` — the type as SPELLED (`bool`,
     * `?Order`, null when untyped), keyed by parameter name so a caller can hold both halves.
     * Promoted constructor parameters are included; a union or intersection reads as null, since it
     * has no single written name.
     *
     * @return array<string, string|null>
     */
    public function paramTypeNames(): array
    {
        if (! $this->isFunctionDeclaration()) {
            return [];
        }

        $types = [];

        foreach ($this->node->params as $param) {
            if (AstNode::variableNameOf($param->var) !== null) {
                $types[$param->var->name] = TypeName::simpleName($param->type);
            }
        }

        return $types;
    }

    /**
     * Is the variable $name read as a CONDITION somewhere in this declaration — the subject of an
     * `if`/`while`, a ternary or match subject, a match-arm condition, or an operand of `&&`/`||`/`!`?
     * The tell that the value is DECIDING something here rather than merely being stored or passed on.
     */
    public function readsAsCondition(string $name): bool
    {
        if ($this->node === null) {
            return false;
        }

        foreach ((new NodeFinder)->findInstanceOf($this->node, Variable::class) as $variable) {
            if ($variable->name !== $name) {
                continue;
            }

            $parent = $variable->getAttribute('parent');

            $decides = $parent instanceof BooleanNot
                || $parent instanceof BooleanAnd
                || $parent instanceof BooleanOr
                || self::isConditionOf($parent, $variable);

            if ($decides) {
                return true;
            }
        }

        return false;
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
        if (! $this->isFunctionDeclaration()) {
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
    protected static function typeToString(?Node $type): ?string
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

    protected static function typeKey(string $type): string
    {
        $type = strtolower(ltrim($type, '?\\'));
        $type = str_replace(['|null', 'null|'], '', $type);

        if (str_contains($type, '|')) {
            $parts = array_filter(array_map(static fn (string $p): string => ltrim($p, '\\'), explode('|', $type)));
            sort($parts);
            $type = implode('|', $parts);
        }

        return $type;
    }

    protected static function scalarLiteral(Node $expr): ?string
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

        return (AstNode::isPropertyRead($subject))
            && $subject->name instanceof Identifier
            && $subject->name->toString() === 'value';
    }

    /**
     * Is this node a property fetch of a fixed member name — `<recv>->{$name}` or `<recv>?->{$name}`?
     * A pure-AST shape check (no type resolution); a detector composes it with a semantic `where` to
     * decide what the receiver IS. (`->value` is a backed enum's magic backing member.)
     */
    public function isPropertyFetchNamed(string $name): bool
    {
        return self::isPropertyRead($this->node) && self::memberNameOf($this->node) === $name;
    }

    /**
     * The NAME of a plain variable — `$order` → `order` — or null for anything else, including one
     * named dynamically (`$$field`), which no static reader can follow. The pair of tests every
     * caller was writing to ask "is this a variable I can name?".
     */
    public static function variableNameOf(?Node $expr): ?string
    {
        return $expr instanceof Variable && is_string($expr->name) ? $expr->name : null;
    }

    /**
     * Must $param be passed — no default, and not the variadic that swallows the rest? The question
     * behind "can this be constructed with no arguments".
     */
    public static function isRequiredParam(Param $param): bool
    {
        return $param->default === null && ! $param->variadic;
    }

    /**
     * The NAME of a promoted constructor parameter — the ones that are also fields — or null when
     * $param promotes nothing, or names itself dynamically.
     */
    public static function promotedParamNameOf(Param $param): ?string
    {
        return $param->flags !== 0 ? self::variableNameOf($param->var) : null;
    }

    /**
     * The class of a `Class::method()` AS WRITTEN — null when the call names it dynamically. The
     * raw-node twin of {@see staticCallClass}, which additionally resolves `self`/`static` and so
     * needs the enclosing context this one does not have.
     */
    public static function staticCallClassNameOf(?Node $expr): ?string
    {
        return $expr instanceof StaticCall && $expr->class instanceof Name ? $expr->class->toString() : null;
    }

    /**
     * The class a `X::class` literal names, or null for anything else — including one whose class is
     * an expression, which no static reader can follow.
     */
    public static function classConstClassOf(?Node $expr): ?string
    {
        return $expr instanceof ClassConstFetch && $expr->class instanceof Name
            ? ltrim($expr->class->toString(), '\\')
            : null;
    }

    /**
     * The name of the class $declaration extends, or null when it extends nothing (or is not a
     * class at all).
     */
    public static function parentClassNameOf(?Node $declaration): ?string
    {
        return $declaration instanceof Class_ && $declaration->extends instanceof Name
            ? $declaration->extends->toString()
            : null;
    }

    /**
     * Is $expr the `null` constant, however it was cased?
     */
    public static function isNullConstant(?Node $expr): bool
    {
        return $expr instanceof ConstFetch && $expr->name->toLowerString() === 'null';
    }

    /**
     * Is $expr a property READ off something — `$o->total`, or `$o?->total`? The nullsafe form asks
     * the same question with a different operator, which is why every caller was writing the pair
     * out by hand.
     */
    public static function isPropertyRead(?Node $expr): bool
    {
        return $expr instanceof PropertyFetch || $expr instanceof NullsafePropertyFetch;
    }

    /**
     * Is $expr a method CALL on something — `$o->total()`, or `$o?->total()`?
     */
    public static function isMethodSend(?Node $expr): bool
    {
        return $expr instanceof MethodCall || $expr instanceof NullsafeMethodCall;
    }

    /**
     * The member NAME a fetch or call writes out — `$o?->total()` → `total` — or null when the node
     * accesses no member, or names one dynamically (`$o->$field`), which no static reader can follow.
     */
    public static function memberNameOf(?Node $expr): ?string
    {
        if (! self::isPropertyRead($expr) && ! self::isMethodSend($expr) && ! $expr instanceof StaticCall) {
            return null;
        }

        return $expr->name instanceof Identifier ? $expr->name->toString() : null;
    }

    /**
     * Is this array literal a projection of the enclosing object's OWN state — a
     * non-empty keyed map whose every value is read off `$this` (`['accessToken'
     * => $this->accessToken, …]`)? That is a `toArray()`/`toValues()` serializer
     * turning a typed object into a persistence/presentation shape — which the
     * value-objects skill exempts — not a loose bag of unrelated fields.
     */
    public function isSelfProjectionArray(): bool
    {
        return $this->arrayProjectionSource() === 'this';
    }

    /**
     * The ONE object this keyed array literal is read FROM — `'this'` for a `toWire()`/`toArray()`
     * serializer, the variable name for `['status' => $outcome->status->value, …]` — or null when it
     * is a real bag: values gathered from several sources, from none, or reaching past that object's
     * own fields into their internals.
     *
     * A field may be DRESSED on the way out and still count: a chain that asks it to serialize itself
     * (`$this->when->toWire()`), a null guard (`$outcome->error === null ? null : …`), a map over it
     * (`array_map(fn (self $t) => $t->toWire(), $this->terms)`). What does NOT count is reading a
     * field's INTERNALS (`$this->origin->lat` — that assembles a new shape out of another type's
     * parts) or calling the object itself (`$this->byCategory()` — computed results, not own state).
     * A backed enum's magic `->value` terminates a chain: it is the field's own scalar, not a reach
     * into an object. So a projection means the type is already there and the array is its wire
     * shape; anything else is the unborn type the value-objects rule is about.
     */
    public function arrayProjectionSource(): ?string
    {
        $values = $this->keyedValues();

        if ($values === null) {
            return null;
        }

        $sources = [];

        foreach ($values as $value) {
            foreach (self::variableNames($value) as $name) {
                $sources[$name] = true;
            }
        }

        if (count($sources) !== 1) {
            return null;
        }

        $source = (string) array_key_first($sources);

        foreach ($values as $value) {
            if (! self::readsOnlyOwnFieldsOf($value, $source)) {
                return null;
            }
        }

        return $source;
    }

    /**
     * The names of every plain variable an expression reads — `['this', 'outcome']`. Anything with a
     * computed name (`$$name`) is not a variable we can name, so it is left out.
     *
     * @return list<string>
     */
    private static function variableNames(?Node $node): array
    {
        $names = [];

        foreach (self::expressionNodes($node) as $child) {
            if (AstNode::variableNameOf($child) !== null) {
                $names[] = $child->name;
            }
        }

        return array_values(array_unique($names));
    }

    /**
     * Every VALUE of this array literal, in declaration order — or null when it is not a non-empty
     * array literal, or carries a spread/positional element, which makes it a list or a merge rather
     * than the named record a projection reads.
     *
     * @return list<Node>|null
     */
    private function keyedValues(): ?array
    {
        if (! $this->node instanceof Array_ || $this->node->items === []) {
            return null;
        }

        $values = [];

        foreach ($this->node->items as $item) {
            if (! $item instanceof ArrayItem || $item->key === null) {
                return null;
            }

            $values[] = $item->value;
        }

        return $values;
    }

    /**
     * Does this value read `$source` only through ITS OWN fields — `$source->field`, dressed by any
     * call or guard — rather than calling `$source` itself or reaching through a field into the
     * object behind it?
     */
    private static function readsOnlyOwnFieldsOf(?Node $value, string $source): bool
    {
        foreach (self::expressionNodes($value) as $node) {
            if (! $node instanceof Variable || $node->name !== $source) {
                continue;
            }

            $field = $node->getAttribute('parent');

            // `$source` used bare, or called on directly (`$this->compute()`) — not a field read.
            if (! $field instanceof PropertyFetch && ! $field instanceof NullsafePropertyFetch) {
                return false;
            }

            if (! self::readsFieldWhole($field->getAttribute('parent'))) {
                return false;
            }
        }

        return true;
    }

    /**
     * Is what sits ABOVE a field read leaving that field WHOLE? A call on it delegates (the field
     * owns its own shape); a further property/index read takes it apart — except a backed enum's
     * magic `->value`, which is the field's own scalar.
     */
    private static function readsFieldWhole(mixed $beyond): bool
    {
        if ($beyond instanceof ArrayDimFetch) {
            return false;
        }

        if (! $beyond instanceof PropertyFetch && ! $beyond instanceof NullsafePropertyFetch) {
            return true;
        }

        return $beyond->name instanceof Identifier && $beyond->name->toString() === 'value';
    }

    /**
     * Every node of an expression, itself included — EXCEPT a nested closure, whose body is its own
     * scope and reads nothing on this expression's behalf.
     *
     * @return list<Node>
     */
    private static function expressionNodes(?Node $node): array
    {
        if ($node === null || $node instanceof Closure || $node instanceof ArrowFunction) {
            return [];
        }

        $nodes = [$node];

        foreach ($node->getSubNodeNames() as $name) {
            foreach (is_array($node->{$name}) ? $node->{$name} : [$node->{$name}] as $child) {
                if ($child instanceof Node) {
                    $nodes = [...$nodes, ...self::expressionNodes($child)];
                }
            }
        }

        return $nodes;
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
    public function enclosingParamIsArray(?string $name): bool
    {
        // No name to look up means no such parameter — not a parameter called ''.
        return $name !== null && self::isArrayType($this->enclosingParamType($name));
    }

    /**
     * The DECLARED type of the enclosing function's `$name` parameter, or null when it has none (or
     * there is no such parameter) — the one read behind "what IS this variable, as written".
     */
    public function enclosingParamType(string $name): ?Node
    {
        foreach (ParamList::of($this->enclosingFunction())->named($name) as $param) {
            return $param->type;
        }

        return null;
    }

    /**
     * The variable a reach is ROOTED at — `$below` in `$below[$id]['kids']`, `$row` in `$row->a->b`.
     * Null when the chain bottoms out at anything else: `$this`, a static property, a call result, a
     * literal. The "whose structure is this?" primitive.
     */
    public function rootVariableName(): ?string
    {
        $node = $this->node;

        while ($node instanceof ArrayDimFetch || AstNode::isPropertyRead($node)) {
            $node = $node->var;
        }

        if (! $node instanceof Variable || ! is_string($node->name) || $node->name === 'this') {
            return null;
        }

        return $node->name;
    }

    /**
     * Does this expression reach into a value the enclosing function was HANDED — a chain rooted at
     * one of its own parameters? The structural line between inspecting your OWN state (whose shape
     * you own, and whose emptiness is an ordinary answer) and inspecting a caller's (whose shape is
     * a PRECONDITION, and so belongs in a guard at the door).
     */
    public function reachesIntoParameter(): bool
    {
        $root = $this->rootVariableName();

        if ($root === null) {
            return false;
        }

        foreach ($this->enclosingFunction()?->getParams() ?? [] as $param) {
            if ($param->var instanceof Variable && $param->var->name === $root) {
                return true;
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
     * The name of the call this node is an ARGUMENT TO — `hasOne` for the `Picker::class` in
     * `$this->hasOne(Picker::class)` — or null when it is not an argument at all.
     *
     * It climbs, because a node is rarely the whole argument: the `Name` inside `Picker::class` and
     * the `Picker::class` around it are both passed to the same call, and a caller holding either
     * deserves the same answer. Named and positional arguments read alike, since both wear an `Arg`.
     * Only the IMMEDIATE call counts — the climb stops at the first call it meets, so a value nested
     * in `hasOne(alias(Picker::class))` is an argument to `alias`, not to `hasOne`.
     */
    public function argumentOfCall(): ?string
    {
        foreach ($this->selfAndAncestors() as $node) {
            if ($node->node instanceof Arg) {
                return $node->parent()->callName();
            }

            if ($node !== $this && $node->callName() !== null) {
                return null;
            }
        }

        return null;
    }

    /**
     * The ATTRIBUTE this node sits inside — `Illuminate\…\ObservedBy` for the `OrderObserver::class`
     * in `#[ObservedBy(OrderObserver::class)]` — or null when it is ordinary code. The attribute twin
     * of {@see argumentOfCall}: an attribute argument is a reference made by a DECLARATION, and what
     * the declaration means is the attribute's to say.
     */
    public function enclosingAttributeName(): ?string
    {
        foreach ($this->selfAndAncestors() as $node) {
            if ($node->node instanceof Attribute) {
                return $node->node->name->toString();
            }
        }

        return null;
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
     * The NAMED arguments of this call — `f(total: 3)` but not `f(3)`. A call knows which of its
     * own arguments carry a name; a caller asking should not have to filter them out itself.
     *
     * @return list<Arg>
     */
    public function namedArguments(): array
    {
        return array_values(array_filter($this->arguments(), static fn (Arg $arg): bool => $arg->name instanceof Identifier));
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
     * Is THIS node an interface declaration? An interface is a contract others implement — the one shape
     * whose implementors may live entirely outside the scanned tree, so "nothing here produces one" never
     * proves it unreachable.
     */
    public function isInterfaceDeclaration(): bool
    {
        return $this->node instanceof Interface_;
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

        return self::declaredClassNameOf($class) ?? $class->name?->toString();
    }

    /**
     * The fully-qualified name php-parser resolved for a class-like DECLARATION, or null for one
     * that has none — an anonymous class. The raw-node twin of {@see enclosingClassName}, which
     * falls back to the written name when the resolver had nothing to qualify.
     */
    public static function declaredClassNameOf(?Node $declaration): ?string
    {
        $name = ($declaration->namespacedName ?? null)?->toString();

        if ($name === null) {
            return null;
        }

        $qualified = ltrim($name, '\\');

        return $qualified === '' ? null : $qualified;
    }

    /**
     * The namespace this node is DECLARED in — `App\Ui\Shared` — or null at global scope. Read from
     * the enclosing `namespace` statement, so it answers for a node at file scope too (a `use`
     * import, a top-level function), where {@see enclosingClassName} has nothing to offer.
     */
    public function namespaceName(): ?string
    {
        $namespace = $this->node instanceof Namespace_
            ? $this->node
            : $this->walkUp(static fn (Node $node): bool => $node instanceof Namespace_);

        return $namespace?->name?->toString();
    }

    /**
     * Is this class-like reference an `use X;` IMPORT rather than a use SITE? An import only lets
     * the file spell the name short; the dependency itself happens where the name is USED — which
     * is where a boundary rule should point the fix, and the only place a declaration-scoped
     * marker can reach.
     */
    public function isImportedName(): bool
    {
        return $this->parent()->node instanceof UseItem;
    }

    /**
     * The fully-qualified class-like name THIS node refers to — for a `Name` node selected by
     * {@see Codebase::whereClassReference} (an import, a type, `new X`, `X::…`). Null on any other
     * node. The other end of a dependency edge, to {@see namespaceName}'s near end.
     */
    public function referencedClassName(): ?string
    {
        return $this->node instanceof Name ? $this->node->toString() : null;
    }

    /**
     * Is this node OUTSIDE any class — i.e. at file/script scope (a route file,
     * a config script, a global helper), where there is no object to inject into?
     */
    public function isOutsideClass(): bool
    {
        return $this->enclosingClass() === null;
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

    protected static function isArrayType(?Node $type): bool
    {
        if ($type instanceof NullableType) {
            return self::isArrayType($type->type);
        }

        return $type instanceof Identifier && $type->name === 'array';
    }

    protected function walkUp(callable $test): ?Node
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

    // ── field-usage reads (a class's OWN fields, `$this->x`) ─────────────────────
    // The reusable substrate for coupling/clump analysis — every walk lives here so a
    // detector composes these instead of hand-rolling a NodeFinder.

    /**
     * Every group of ≥2 DISTINCT own fields (`$this->a`, `$this->b`) ASSEMBLED INTO ONE VALUE together —
     * the direct arguments of a `new X($this->a, $this->b)` or the items of a tuple `[$this->a, $this->b]`.
     * Only object construction and array literals count: a plain call (`sprintf($this->a, $this->b)`, a
     * method passing them along) is formatting/forwarding, not assembling one thing, so it is NOT a group.
     *
     * @return list<list<string>>
     */
    public function selfPropertyGroupsAssembled(): array
    {
        $groups = [];

        foreach ($this->assemblingExpressions() as $expression) {
            $fields = [];

            foreach ($this->directArgumentValues($expression) as $value) {
                $name = self::selfPropertyOf($value);

                if ($name !== null) {
                    $fields[$name] = true;
                }
            }

            if (count($fields) >= 2) {
                $groups[] = array_keys($fields);
            }
        }

        return $groups;
    }

    /**
     * The own fields this node tests for ABSENCE — `$this->x === null` / `!== null`, plus
     * `$this->x instanceof $optionalFqcn` when a marker FQCN (e.g. Spatie `Optional`) is given.
     *
     * @return list<string>
     */
    public function selfPropertiesTestedForAbsence(?string $optionalFqcn = null): array
    {
        if ($this->node === null) {
            return [];
        }

        $tested = [];

        foreach ((new NodeFinder)->find($this->node, static fn (Node $n): bool => $n instanceof Identical || $n instanceof NotIdentical) as $comparison) {
            /**
             * @var Identical|NotIdentical $comparison
             */
            foreach ([[$comparison->left, $comparison->right], [$comparison->right, $comparison->left]] as [$side, $other]) {
                if ($other instanceof ConstFetch && strtolower($other->name->toString()) === 'null' && ($name = self::selfPropertyOf($side)) !== null) {
                    $tested[$name] = true;
                }
            }
        }

        if ($optionalFqcn !== null) {
            foreach ((new NodeFinder)->findInstanceOf($this->node, Instanceof_::class) as $instance) {
                if ($instance->class instanceof Name
                    && ltrim($instance->class->toString(), '\\') === ltrim($optionalFqcn, '\\')
                    && ($name = self::selfPropertyOf($instance->expr)) !== null) {
                    $tested[$name] = true;
                }
            }
        }

        return array_keys($tested);
    }

    /**
     * Every place a combining expression under this node pairs a DIRECT own field with a reach THROUGH a
     * sibling object field — `[$this->a, $this->b->c]` → `['a', 'b', 'c']` (a ≠ b). The reusable walk; the
     * caller resolves and compares the TYPES (a same-type pair is a split-boundary clump; a different-type
     * pair is an aggregate holding related-but-distinct parts).
     *
     * @return list<array{0: string, 1: string, 2: string}>  [directField, reachBase, reachedProperty]
     */
    public function selfFieldNestedReachTriples(): array
    {
        $triples = [];

        foreach ($this->combiningExpressions() as $expression) {
            [$directs, $reaches] = self::directFieldsAndReaches($expression);

            foreach ($reaches as [$base, $property]) {
                foreach ($directs as $direct) {
                    if ($direct !== $base) {
                        $triples[] = [$direct, $base, $property];
                    }
                }
            }
        }

        return $triples;
    }

    /**
     * Is this class's property $name ever assigned OUTSIDE its constructor — a live copy, not a frozen
     * field? (Reads the enclosing class; a promoted/`readonly` field that is never re-assigned yields false.)
     */
    public function rewritesSelfPropertyOutsideConstructor(string $name): bool
    {
        $class = $this->enclosingClass();

        if ($class === null) {
            return false;
        }

        foreach ($class->getMethods() as $method) {
            if ($method->name->toString() === '__construct') {
                continue;
            }

            foreach ((new NodeFinder)->findInstanceOf($method, Assign::class) as $assign) {
                if (self::selfPropertyOf($assign->var) === $name) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * The `$this->NAME` field a node reads, or null when it isn't a `$this->` property fetch.
     */
    protected static function selfPropertyOf(Node $node): ?string
    {
        return $node instanceof PropertyFetch
            && $node->var instanceof Variable
            && $node->var->name === 'this'
            && $node->name instanceof Identifier
            ? $node->name->toString()
            : null;
    }

    /**
     * The value-ASSEMBLING expressions under this node — a `new X(...)` or an array literal `[...]`. These
     * build ONE value out of their parts; a call does not, so it is excluded (see {@see combiningExpressions}).
     *
     * @return list<Node>
     */
    protected function assemblingExpressions(): array
    {
        return $this->node === null ? [] : (new NodeFinder)->find($this->node, static fn (Node $n): bool =>
            $n instanceof New_ || $n instanceof Array_);
    }

    /**
     * The value-COMBINING expressions — assembling ones PLUS calls — used where forwarding fields into a
     * call still counts (the cross-object reach). A superset of {@see assemblingExpressions}.
     *
     * @return list<Node>
     */
    protected function combiningExpressions(): array
    {
        return $this->node === null ? [] : (new NodeFinder)->find($this->node, static fn (Node $n): bool =>
            $n instanceof New_ || $n instanceof Array_ || $n instanceof MethodCall || $n instanceof StaticCall || $n instanceof FuncCall);
    }

    /**
     * The direct argument/item value expressions of a combining node — `new X($a, $b)` / `[$a, $b]` / `f($a)`.
     *
     * @return list<Node>
     */
    protected function directArgumentValues(Node $node): array
    {
        if ($node instanceof Array_) {
            return array_values(array_map(static fn (ArrayItem $item): Node => $item->value, array_filter($node->items)));
        }

        $values = [];

        foreach ($node instanceof New_ || $node instanceof MethodCall || $node instanceof StaticCall || $node instanceof FuncCall ? $node->args : [] as $argument) {
            if ($argument instanceof Arg) {
                $values[] = $argument->value;
            }
        }

        return $values;
    }

    /**
     * The direct own fields and the `$this->b->c` reaches within one expression.
     *
     * @return array{0: list<string>, 1: list<array{0: string, 1: string}>}  [directFields, [base, property]…]
     */
    protected static function directFieldsAndReaches(Node $node): array
    {
        $directs = [];
        $reaches = [];

        foreach ((new NodeFinder)->findInstanceOf($node, PropertyFetch::class) as $fetch) {
            $parent = $fetch->getAttribute('parent');

            // `$this->b` used as the receiver of `$this->b->c` — a reach base plus the property reached.
            if ($parent instanceof PropertyFetch && $parent->var === $fetch
                && ($base = self::selfPropertyOf($fetch)) !== null
                && $parent->name instanceof Identifier) {
                $reaches[] = [$base, $parent->name->toString()];
            } elseif (($name = self::selfPropertyOf($fetch)) !== null) {
                $directs[] = $name;
            }
        }

        return [array_values(array_unique($directs)), $reaches];
    }
}
