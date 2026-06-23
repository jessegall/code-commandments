<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Prophets\Backend;

use JesseGall\CodeCommandments\Attributes\IntroducedIn;
use JesseGall\CodeCommandments\Commandments\PhpCommandment;
use JesseGall\CodeCommandments\Contracts\NeedsCodebaseIndex;
use JesseGall\CodeCommandments\Results\Advisory;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Results\Tier;
use JesseGall\CodeCommandments\Support\CallGraph\CodebaseIndex;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;

/**
 * Flag a nullable return whose absence is never actually tolerated (#98 H2): a
 * PRIVATE method declares `?T` / `T | null` / `Option<T>`, yet EVERY call site
 * immediately de-nulls it — `?? throw`, `->getOrThrow()`/`->unwrap()`, or a blind
 * `->` dereference. The nullability is unearned ceremony: make the method total
 * (always return a value — a Null Object, an exhaustive match) or THROW at the
 * source, so the contract is honest in one place instead of re-asserted at every
 * caller.
 *
 * Scoped to PRIVATE methods so "every caller" is provable from the class alone —
 * no hidden out-of-file callers, no index, no false positives. The sibling rungs
 * of the same ladder live in other prophets: a closed-enum `default => null` →
 * {@see ThrowOnUnhandledCaseProphet}; a registry miss → {@see RegistryReturnContractProphet};
 * a nullable param normalised to a default → {@see PreferNullObjectDefaultsProphet};
 * a genuine value-or-nothing → {@see PreferOptionOverNullProphet}.
 *
 * Advisory, never a sin; not auto-fixable (totalising is a design call).
 */
#[IntroducedIn('1.134.0')]
class PreferTotalOverNullableProphet extends PhpCommandment implements NeedsCodebaseIndex
{
    /** Option de-null methods — calling one treats absence as impossible. */
    private const UNWRAP_METHODS = ['getorthrow', 'getorfail', 'unwrap'];

    /** Scalar return types and the empty identity to return instead of null. */
    private const SCALAR_IDENTITY = [
        'array' => '[]',
        'string' => "''",
        'int' => '0',
        'float' => '0.0',
        'bool' => 'false',
    ];

    private ?CodebaseIndex $index = null;

    public function setCodebaseIndex(CodebaseIndex $index): void
    {
        $this->index = $index;
    }

    public function description(): string
    {
        return 'Make a method total or throw when every caller already de-nulls its nullable return';
    }

    protected function defaultTier(): Tier
    {
        return Tier::Convention;
    }

    public function advisory(): Advisory
    {
        return Advisory::make()
            ->applyWhen('A PRIVATE method returns `?T` / `T | null` / `Option<T>`, but every call site immediately requires the value — `?? throw`, `->getOrThrow()`/`->unwrap()`, or a blind `->` dereference. The absence is never handled, so the nullability is unearned.')
            ->leaveWhen('any caller genuinely handles the absence — branches on null, supplies a real `?? $default`, uses `?->`, or passes it on as optional. Then the absence is real: keep it (as `Option<T>`, not raw null).')
            ->whenUnsure('only when NO caller tolerates "no value": make the method TOTAL or THROW at the source. If T has an empty IDENTITY (a Fluent bag, scalar, no-arg/`::empty()` class) AND that empty value is a valid domain value, returning it is the total form; if the empty value would be wrong (e.g. `\'\'` for a path), throw instead.');
    }

    public function detailedDescription(): string
    {
        return <<<'SCRIPTURE'
A nullable return is a promise that "no value" is a real, handleable outcome.
When EVERY caller immediately un-hedges it — `?? throw`, `->getOrThrow()`, or a
blind `->` deref that assumes non-null — the promise was a lie: the value always
had to be there. The nullability just spreads defensive ceremony across callers
for a state none of them accept.

Bad — the method hedges, every caller un-hedges:
    private function root(): ?TreeNode { … }
    // caller A:
    $this->root() ?? throw new RuntimeException('no root');
    // caller B:
    $this->root()->id;                          // blind deref — assumes non-null

Good — make the contract honest once, at the source:
    private function root(): TreeNode
    {
        return $this->root ?? throw EmptyTreeException::create();
    }

THE EMPTY-IDENTITY CASE — when T has a natural ZERO value, the honest total form
is NOT throw, it is "return the empty T". `Option<T>` is NOT a fix here — it is
the same partiality wearing a nicer coat, and it gets waved through review
*because* it looks like the blessed pattern:

    // ❌ nullable — null is not a real outcome; an unreadable file just has no data
    private function decode(string $p): ?ValueBag { … return null; }
    // ❌ Option — same partiality; every caller still un-hedges (->getOrThrow()/->getOr(new ValueBag))
    /** @return Option<ValueBag> */
    private function decode(string $p): Option { … return Option::none(); }

    // ✅ total — ValueBag (a Fluent bag) has an empty identity: "no data" IS an empty bag
    public function decode(string $p): ValueBag
    {
        return is_file($p) ? ValueBag::fromJson(...) : new ValueBag;
    }
    // caller: $this->decode($p)->get('x')   — no null check, no Option ceremony

Empty identities: `array`→`[]`, `string`→`''`, `int`/`float`→`0`/`0.0`,
`bool`→`false`, a `Fluent`/Collection subclass or a no-arg/`::empty()` class →
`new T` / `T::empty()`.

WHAT FIRES — a PRIVATE method whose return is `?T` / `T | null` / `Option<T>`,
with >= 1 in-class call site, where EVERY call site de-nulls the result
(`$this->m() ?? throw …`, `->getOrThrow()`/`->unwrap()`, or a blind `->` deref).
When T ALSO has an empty identity (a Fluent bag, scalar, no-arg/`::empty()`
class), the remedy adds "return the empty T" alongside "or throw".

WHAT DOES NOT — ANY caller that handles the absence keeps the nullable earned:
a `?? $realDefault`, a `?->` chain, a `=== null` branch, passing it on, OR an
Option consumed with `->getOr($default)` / `->transform(...)->getOr(...)` (a
default IS handling the miss — even for an empty-identity type, where returning
the empty value may be a wrong domain value, e.g. `''` for a path). A non-private
method (callers may live elsewhere). Advisory: return-empty / totalise / throw
is a design decision.
SCRIPTURE;
    }

    public function judge(string $filePath, string $content): Judgment
    {
        $ast = $this->parse($content);

        if ($ast === null) {
            return $this->righteous();
        }

        // Resolve names in place (without replacing nodes, so byte positions and
        // node identities used elsewhere stay intact) so a class return type
        // carries its FQCN for the codebase-index empty-identity lookup.
        (new NodeTraverser(new NameResolver(null, ['replaceNodes' => false])))->traverse($ast);

        $finder = new NodeFinder;
        $parents = [];
        $this->buildParentMap($ast, null, $parents);
        $warnings = [];

        foreach ($finder->findInstanceOf($ast, Node\Stmt\Class_::class) as $class) {
            foreach ($class->getMethods() as $method) {
                $kind = $method->isPrivate() && ! $method->isStatic() ? $this->returnKind($method) : null;

                if ($kind === null) {
                    continue;
                }

                $calls = $this->callsTo($class, $method->name->toString(), $finder);

                if ($calls === []) {
                    continue;
                }

                // The trigger is ALWAYS "every caller de-nulls" — if ANY caller
                // handles the absence (`?? $default`, `?->`, an Option
                // `->getOr($default)`/`->transform(...)->getOr(...)`, a `=== null`
                // branch), the nullability is earned, keep it. (#115: do NOT fire
                // on an empty-identity type whose caller already handles absence —
                // e.g. `viteConfigPath(): Option<string>` consumed via
                // `->transform(...)->getOr(false)`; returning `''` would also be a
                // wrong "empty identity" for a path.)
                if (! $this->everyCallDeNulls($calls, $kind, $parents)) {
                    continue;
                }

                // The empty identity (a Fluent bag, scalar, no-arg/`::empty()`
                // class) only ENRICHES the remedy — "return the empty T" alongside
                // "or throw" — it does not widen the trigger (#108).
                $identity = $this->emptyIdentity($method, $ast, $finder);

                $line = $method->getStartLine();
                $warnings[] = $this->warningAt(
                    $line,
                    $this->messageFor($method->name->toString(), $kind, $identity),
                    $this->lineSnippet($content, $line),
                    'unearned-nullable:' . $method->name->toString(),
                );
            }
        }

        return $warnings === [] ? $this->righteous() : Judgment::withWarnings($warnings);
    }

    /**
     * @param  'nullable'|'option'  $kind
     */
    private function messageFor(string $method, string $kind, ?string $identity): string
    {
        $hedge = $kind === 'option' ? 'an Option<T>' : 'a nullable';

        if ($identity !== null) {
            return sprintf(
                '%s() returns %s, but T has an empty identity — null/none is partiality the type can already represent. Return the empty value (`%s`) when absence is benign, or throw a named exception at the source when it is a bug. Wrapping it in %s just makes every caller un-hedge a state the type owns.',
                $method,
                $hedge,
                $identity,
                $kind === 'option' ? 'Option' : 'a nullable',
            );
        }

        return sprintf(
            '%s() returns %s, but every call site immediately requires the value (`?? throw` / `->getOrThrow()` / a blind `->` deref) — the absence is never handled. Make it total (a Null Object / exhaustive match) or throw a named exception at the source, so the contract is honest once instead of re-asserted at every caller.',
            $method,
            $hedge,
        );
    }

    /**
     * The empty identity to return instead of null/none — `[]`/`''`/`0`/`0.0`/
     * `false` for a scalar, `new T` / `T::empty()` for a class that has one, or
     * null when the type has no derivable empty identity (→ totalise-or-throw).
     *
     * @param  array<Node>  $ast
     */
    private function emptyIdentity(Node\Stmt\ClassMethod $method, array $ast, NodeFinder $finder): ?string
    {
        $inner = $this->innerReturnType($method);

        if ($inner === null) {
            return null;
        }

        if ($inner instanceof Node\Identifier) {
            return self::SCALAR_IDENTITY[strtolower($inner->toString())] ?? null;
        }

        // A class name: scalar pseudo-types may also arrive as a Name.
        $short = $inner->getLast();

        if (isset(self::SCALAR_IDENTITY[strtolower($short)])) {
            return self::SCALAR_IDENTITY[strtolower($short)];
        }

        return $this->classEmptyIdentity($inner, $short, $ast, $finder);
    }

    /**
     * The non-null inner type of the return: the inner of `?T` / `T | null`, or
     * the `Inner` of an `@return Option<Inner>` docblock. Null when not derivable.
     */
    private function innerReturnType(Node\Stmt\ClassMethod $method): Node\Identifier|Node\Name|null
    {
        $type = $method->returnType;

        if ($type instanceof Node\NullableType) {
            return $type->type instanceof Node\Identifier || $type->type instanceof Node\Name ? $type->type : null;
        }

        if ($type instanceof Node\UnionType) {
            foreach ($type->types as $member) {
                if (($member instanceof Node\Identifier || $member instanceof Node\Name)
                    && strtolower($member->toString()) !== 'null'
                ) {
                    return $member;
                }
            }

            return null;
        }

        // Option<Inner> — the inner type lives in the @return docblock.
        if ($type instanceof Node\Name && str_ends_with($type->getLast(), 'Option')) {
            $doc = $method->getDocComment()?->getText();

            if ($doc !== null && preg_match('/@return\s+\\\\?[A-Za-z0-9_\\\\]*Option<\s*\\\\?([A-Za-z0-9_\\\\]+)\s*>/', $doc, $m) === 1) {
                $innerName = $m[1];

                // A scalar pseudo-type → Identifier; anything else is a class Name
                // (so classEmptyIdentity resolves it, in-file or via the index).
                return isset(self::SCALAR_IDENTITY[strtolower($innerName)])
                    ? new Node\Identifier($innerName)
                    : new Node\Name($innerName);
            }
        }

        return null;
    }

    /**
     * Whether the class type has an empty identity: it extends a Fluent /
     * Collection base, exposes a static `empty()` / no-arg `make()`, or is
     * constructible with no arguments. Resolved from the same file or the
     * codebase index. Returns `new <Short>` / `<Short>::empty()` / `::make()`.
     *
     * @param  array<Node>  $ast
     */
    private function classEmptyIdentity(Node\Name $name, string $short, array $ast, NodeFinder $finder): ?string
    {
        $class = $this->findClassNode($name, $short, $ast, $finder);

        if ($class === null) {
            return null;
        }

        if ($class->extends instanceof Node\Name) {
            $parent = $class->extends->getLast();

            if ($parent === 'Fluent' || str_ends_with($parent, 'Collection')) {
                return 'new ' . $short;
            }
        }

        foreach ($class->getMethods() as $m) {
            if (! $m->isStatic() || ! $m->isPublic()) {
                continue;
            }

            $mName = strtolower($m->name->toString());

            if ($mName === 'empty') {
                return $short . '::empty()';
            }

            if ($mName === 'make' && $this->requiredParamCount($m) === 0) {
                return $short . '::make()';
            }
        }

        $ctor = $class->getMethod('__construct');

        if ($ctor === null || $this->requiredParamCount($ctor) === 0) {
            return 'new ' . $short;
        }

        return null;
    }

    /**
     * The class node for a return type — defined in this file, or located via the
     * codebase index by FQCN (resolved name) and parsed.
     *
     * @param  array<Node>  $ast
     */
    private function findClassNode(Node\Name $name, string $short, array $ast, NodeFinder $finder): ?Node\Stmt\Class_
    {
        foreach ($finder->findInstanceOf($ast, Node\Stmt\Class_::class) as $class) {
            if ($class->name?->toString() === $short) {
                return $class;
            }
        }

        $resolved = $name->getAttribute('resolvedName');
        $fqcn = $resolved instanceof Node\Name ? $resolved->toString() : ($name->isFullyQualified() ? ltrim($name->toString(), '\\') : null);

        if ($fqcn === null || $this->index === null) {
            return null;
        }

        $summary = $this->index->classByFqcn(ltrim($fqcn, '\\'));

        if ($summary === null) {
            return null;
        }

        $content = @file_get_contents($summary->filePath);

        if (! is_string($content)) {
            return null;
        }

        $classAst = $this->parse($content);

        if ($classAst === null) {
            return null;
        }

        foreach ((new NodeFinder)->findInstanceOf($classAst, Node\Stmt\Class_::class) as $class) {
            if ($class->name?->toString() === $short) {
                return $class;
            }
        }

        return null;
    }

    private function requiredParamCount(Node\Stmt\ClassMethod $method): int
    {
        $required = 0;

        foreach ($method->params as $param) {
            if ($param->default === null && ! $param->variadic) {
                $required++;
            }
        }

        return $required;
    }

    /**
     * Classify the method's nullable return, or null if it isn't nullable:
     *  - 'nullable' — native `?T` / `T | null`. De-null = blind `->` deref or `?? throw`.
     *  - 'option'   — an `Option`-typed return. De-null = `->getOrThrow()`/`->unwrap()`
     *                 ONLY; `->getOr($default)` / `->map(…)` TOLERATE the absence.
     *
     * @return 'nullable'|'option'|null
     */
    private function returnKind(Node\Stmt\ClassMethod $method): ?string
    {
        $type = $method->returnType;

        if ($type instanceof Node\NullableType) {
            return 'nullable';
        }

        if ($type instanceof Node\UnionType) {
            foreach ($type->types as $member) {
                if ($member instanceof Node\Identifier && strtolower($member->toString()) === 'null') {
                    return 'nullable';
                }
            }

            return null;
        }

        return ($type instanceof Node\Name && str_ends_with($type->getLast(), 'Option')) ? 'option' : null;
    }

    /**
     * Every `$this->$name(...)` call within the class.
     *
     * @return list<Expr\MethodCall>
     */
    private function callsTo(Node\Stmt\Class_ $class, string $name, NodeFinder $finder): array
    {
        $calls = [];

        foreach ($finder->findInstanceOf($class->stmts, Expr\MethodCall::class) as $call) {
            if ($call->name instanceof Node\Identifier
                && $call->name->toString() === $name
                && $call->var instanceof Expr\Variable
                && $call->var->name === 'this'
            ) {
                $calls[] = $call;
            }
        }

        return $calls;
    }

    /**
     * @param  list<Expr\MethodCall>  $calls
     * @param  'nullable'|'option'  $kind
     * @param  array<int, Node>  $parents
     */
    private function everyCallDeNulls(array $calls, string $kind, array $parents): bool
    {
        foreach ($calls as $call) {
            if (! $this->callDeNulls($call, $kind, $parents)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Whether this call's result is immediately required (the absence rejected).
     * The rule differs by return kind — an `Option` has tolerant de-null methods
     * (`->getOr($default)`, `->map(…)`) that a native `?T` does not:
     *  - 'option'   — ONLY a `->getOrThrow()`/`->unwrap()` call requires it. Any
     *                 other chained method (`->getOr`, `->map`, `->andThen`) HANDLES
     *                 the none, so the Option is earned — not a de-null.
     *  - 'nullable' — `$this->m() ?? throw …`, or a PLAIN (non-nullsafe) `->member` /
     *                 `->method()` deref (assumes non-null). `?->` and `?? $default`
     *                 tolerate it.
     *
     * @param  'nullable'|'option'  $kind
     * @param  array<int, Node>  $parents
     */
    private function callDeNulls(Expr\MethodCall $call, string $kind, array $parents): bool
    {
        $parent = $parents[spl_object_id($call)] ?? null;

        if ($parent === null) {
            return false;
        }

        if ($kind === 'option') {
            return $parent instanceof Expr\MethodCall
                && $parent->var === $call
                && $parent->name instanceof Node\Identifier
                && in_array(strtolower($parent->name->toString()), self::UNWRAP_METHODS, true);
        }

        // 'nullable': `$this->m() ?? throw …` — coalesce whose fallback is a throw.
        if ($parent instanceof Expr\BinaryOp\Coalesce && $parent->left === $call) {
            return $parent->right instanceof Expr\Throw_;
        }

        // `$this->m()->...` — a PLAIN (non-nullsafe) method/property access requires
        // the result to be non-null.
        if ($parent instanceof Expr\MethodCall && $parent->var === $call) {
            return true;
        }

        return $parent instanceof Expr\PropertyFetch && $parent->var === $call;
    }

    /**
     * @param  array<int, Node>  $parents
     */
    private function buildParentMap(array $nodes, ?Node $parent, array &$parents): void
    {
        foreach ($nodes as $node) {
            if (! $node instanceof Node) {
                continue;
            }

            if ($parent !== null) {
                $parents[spl_object_id($node)] = $parent;
            }

            foreach ($node->getSubNodeNames() as $subName) {
                $child = $node->{$subName};
                $children = is_array($child) ? $child : [$child];
                $this->buildParentMap($children, $node, $parents);
            }
        }
    }

}
