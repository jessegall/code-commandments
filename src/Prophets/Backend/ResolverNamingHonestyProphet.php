<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Prophets\Backend;

use JesseGall\CodeCommandments\Attributes\IntroducedIn;
use JesseGall\CodeCommandments\Commandments\PhpCommandment;
use JesseGall\CodeCommandments\Results\Advisory;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Results\Tier;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\NodeFinder;

/**
 * Naming honesty for `*Resolver` classes (#82). In a codebase with a first-class
 * Resolver/Predicate kernel, the `Resolver` suffix sets an expectation:
 * "first-match dispatch via the kernel". A class that carries the name but does
 * NO first-match dispatch at all — a registry lookup, a reflection accessor, a
 * string interpolator, a recursive flatten — is MISNAMED, and the suffix has
 * stopped meaning anything.
 *
 * This is the inverse, low-false-positive half of the rule: it fires only on the
 * crisp signal "named Resolver + no dispatch". The "named Resolver that
 * hand-rolls dispatch → adopt the kernel" half is {@see ResolverPatternProphet}'s
 * job, so this prophet stays silent when any dispatch is present (kernel OR
 * hand-rolled), to avoid double-flagging.
 *
 * Advisory, never a sin — renaming is a cross-repo refactor and sometimes the
 * domain's ubiquitous language wins; emit guidance only.
 *
 *
 *
 *
 * @method-generated-start
 * @method static suffix(string $value)
 * @method-generated-end
 */
#[IntroducedIn('1.127.0')]
class ResolverNamingHonestyProphet extends PhpCommandment
{
    private const DEFAULT_SUFFIX = 'Resolver';

    /** Static kernel entrypoints — a class that calls one genuinely resolves. */
    private const KERNEL_STATICS = ['firstresultwins', 'collect', 'using'];

    public function description(): string
    {
        return 'A *Resolver should do first-match dispatch (ideally via the kernel) — otherwise rename off the suffix';
    }

    protected function defaultTier(): Tier
    {
        return Tier::Convention;
    }

    public function advisory(): Advisory
    {
        return Advisory::make()
            ->applyWhen('A class named `*Resolver` does NO first-match dispatch — no kernel use (`Resolver::firstResultWins`/`->then()`), no chain of guard-returns, no `match(true)`, no first-non-null `??` candidate chain. It is a lookup / reader / transformer / factory wearing a misleading name.')
            ->leaveWhen('the class genuinely resolves (uses the kernel or hand-rolls first-match dispatch — the latter is ResolverPattern\'s concern, not this one), thinly wraps a kernel resolver, or the domain\'s ubiquitous language really does call this a resolver.')
            ->whenUnsure('ask what the class DOES: maps a key to a registered value → `*Registry`/`*Map`; reads a property/attribute → `*Reader`/`*Accessor`; builds a thing → `*Factory`; interpolates a string → `*Interpolator`. If it dispatches an input to one of several outputs by a chain of conditions, keep the name and adopt the kernel.');
    }

    public function detailedDescription(): string
    {
        return <<<'SCRIPTURE'
The `Resolver` suffix is a promise: "I take an input and dispatch it to one of
several outputs by a chain of conditions — first match wins." In a codebase that
ships a Resolver/Predicate kernel, the name specifically implies that kernel. A
class named `*Resolver` that does no dispatch at all breaks the promise — the
reader expects a dispatch table and finds a lookup, a reflection read, or a
string templater.

Misnamed — no dispatch, just a registry lookup:
    final class TriggerEventKeyResolver
    {
        public function resolve(string $key): EventKey
        {
            return $this->map[$key] ?? throw new UnknownKey($key);
        }
    }
    // → it is a registry/map, not a resolver: `TriggerEventKeyRegistry`/`…Map`.

Other misnamed shapes and their honest names:
    PipeResolver (if/instanceof)            → PipeFactory / PipeLocator
    BagTokenResolver (string interpolation) → BagTokenInterpolator
    AttributeResolver (reflection read)     → AttributeReader / AttributeAccessor
    StepResolver (recursive flatten)        → StepFlattener / StepExpander

Genuinely a resolver — KEEP the name (this prophet stays silent):
    Resolver::firstResultWins(
        IsScalarType::make()->then(FieldFactory::scalar()),
        IsObjectType::for($this->objects)->then(FieldFactory::object($this->objects)),
    );

WHAT FIRES — a class whose name ends in `Resolver` that contains NONE of: a
kernel call (`Resolver::firstResultWins`/`collect`/`using`, or `->then(...)`); a
chain of guard-returns (`if (cond) return X;` × 2+); a `match (true)` over
predicate arms; a first-non-null `??` candidate chain
(`$this->a(...) ?? $this->b(...)`); or a `foreach { if (...) return ...; }`.

WHAT DOES NOT — a `*Resolver` that uses the kernel (correct) or that hand-rolls
first-match dispatch (that is ResolverPattern's push to adopt the kernel, not a
naming problem), a class wrapping a kernel resolver, or a single binary branch
where the kernel would add more ceremony than it removes.

Advisory — renaming ripples across the codebase, and occasionally the domain
language wins. Weigh it; this is guidance, not a blocker. Not auto-fixable.
SCRIPTURE;
    }

    public function judge(string $filePath, string $content): Judgment
    {
        $ast = $this->parse($content);

        if ($ast === null) {
            return $this->righteous();
        }

        $warnings = [];

        foreach ((new NodeFinder)->findInstanceOf($ast, Node\Stmt\Class_::class) as $class) {
            if ($class->name === null || ! $this->isResolverName($class) || $this->isAbstractOrInterfaceLike($class)) {
                continue;
            }

            // Wraps / extends a kernel resolver base → genuinely a resolver.
            if ($this->extendsResolverBase($class)) {
                continue;
            }

            // Implements a `*Resolver`/`*Strategy` interface — a deliberately named
            // member of a resolver/strategy family (the parent dispatches BETWEEN
            // these; the individual strategy does the work). Domain ubiquitous
            // language wins (#82 LEAVE-WHEN) — leave it.
            if ($this->implementsResolverFamilyInterface($class)) {
                continue;
            }

            if ($this->hasAnyDispatch($class)) {
                continue;
            }

            $name = $class->name->toString();
            $warnings[] = $this->warningAt(
                $class->getStartLine(),
                sprintf('%s is named `*Resolver` but does no first-match dispatch — no kernel use, no guard-return chain, no `match(true)`, no first-non-null `??` chain. It is misnamed: rename to its real role (`*Registry`/`*Map` for a lookup, `*Reader`/`*Accessor` for a read, `*Factory` for a build, `*Interpolator` for a string template).', $name),
                $this->lineSnippet($content, $class->getStartLine()),
                'resolver-misnamed:' . $name,
            );
        }

        return $warnings === [] ? $this->righteous() : Judgment::withWarnings($warnings);
    }

    private function isResolverName(Node\Stmt\Class_ $class): bool
    {
        $suffix = (string) $this->config('suffix', self::DEFAULT_SUFFIX);

        return $suffix !== '' && $class->name !== null && str_ends_with($class->name->toString(), $suffix);
    }

    private function isAbstractOrInterfaceLike(Node\Stmt\Class_ $class): bool
    {
        // An abstract base named `*Resolver` is the kernel's own root — leave it.
        return $class->isAbstract();
    }

    private function implementsResolverFamilyInterface(Node\Stmt\Class_ $class): bool
    {
        foreach ($class->implements as $interface) {
            $short = $interface->getLast();

            if (str_ends_with($short, 'Resolver') || str_ends_with($short, 'ResolverStrategy') || str_ends_with($short, 'Strategy')) {
                return true;
            }
        }

        return false;
    }

    private function extendsResolverBase(Node\Stmt\Class_ $class): bool
    {
        if ($class->extends === null) {
            return false;
        }

        $extends = ltrim($class->extends->toString(), '\\');
        $bases = $this->config('base_classes', ['Resolver', 'ResolverDecorator']);
        $bases = is_array($bases) ? $bases : [];

        foreach ($bases as $base) {
            $baseShort = $this->shortName(ltrim((string) $base, '\\'));

            if ($extends === $baseShort || str_ends_with($extends, '\\' . $baseShort)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether the class contains ANY first-match dispatch — kernel or hand-rolled.
     * If it does, this is not a naming problem (it either resolves correctly, or
     * ResolverPattern handles the hand-rolled case), so this prophet stays silent.
     */
    private function hasAnyDispatch(Node\Stmt\Class_ $class): bool
    {
        $finder = new NodeFinder;

        return $this->usesKernel($finder, $class)
            || $this->hasGuardReturnChain($finder, $class)
            || $this->hasMatchTrueDispatch($finder, $class)
            || $this->hasFirstNonNullChain($finder, $class)
            || $this->hasForeachFirstMatch($finder, $class);
    }

    private function usesKernel(NodeFinder $finder, Node\Stmt\Class_ $class): bool
    {
        // The kernel itself — a class that DECLARES firstResultWins/collect/using
        // static factories IS the Resolver kernel, not a misnamed service.
        foreach ($class->getMethods() as $method) {
            if ($method->isStatic() && in_array(strtolower($method->name->toString()), self::KERNEL_STATICS, true)) {
                return true;
            }
        }

        foreach ($finder->findInstanceOf($class->stmts, Expr\StaticCall::class) as $call) {
            if ($call->class instanceof Node\Name
                && $this->shortName($call->class->toString()) === 'Resolver'
                && $call->name instanceof Node\Identifier
                && in_array(strtolower($call->name->toString()), self::KERNEL_STATICS, true)
            ) {
                return true;
            }
        }

        // A `->then(...)` anywhere is a Predicate-kernel pairing.
        foreach ($finder->findInstanceOf($class->stmts, Expr\MethodCall::class) as $call) {
            if ($call->name instanceof Node\Identifier && strtolower($call->name->toString()) === 'then') {
                return true;
            }
        }

        return false;
    }

    private function hasGuardReturnChain(NodeFinder $finder, Node\Stmt\Class_ $class): bool
    {
        foreach ($class->getMethods() as $method) {
            if ($method->stmts === null) {
                continue;
            }

            // Conservative: even ONE guard-return is dispatch-like, so we stay
            // silent (a single binary branch is the author's judgment per #82's
            // LEAVE-WHEN; a 2+ chain is ResolverPattern's push to the kernel).
            // Only a class with NO conditional-return at all reads as "no dispatch".
            foreach ($finder->findInstanceOf($method->stmts, Node\Stmt\If_::class) as $if) {
                if ($if->else === null && $if->elseifs === []
                    && $this->returnsOrThrows($if->stmts)
                ) {
                    return true;
                }
            }
        }

        return false;
    }

    private function hasMatchTrueDispatch(NodeFinder $finder, Node\Stmt\Class_ $class): bool
    {
        foreach ($finder->findInstanceOf($class->stmts, Expr\Match_::class) as $match) {
            if ($match->cond instanceof Expr\ConstFetch && strtolower($match->cond->name->toString()) === 'true') {
                return true;
            }
        }

        return false;
    }

    private function hasFirstNonNullChain(NodeFinder $finder, Node\Stmt\Class_ $class): bool
    {
        foreach ($finder->findInstanceOf($class->stmts, Expr\BinaryOp\Coalesce::class) as $coalesce) {
            // `$this->fieldCandidate(...) ?? $this->handleCandidate(...)` — a
            // first-non-null choice between two CALLS is hand-rolled dispatch.
            if ($this->isCallish($coalesce->left) && $this->isCallish($coalesce->right)) {
                return true;
            }

            // `a[..] ?? b[..] ?? default` — a CHAIN of 2+ `??` selecting the first
            // present candidate is first-non-null dispatch. `??` is RIGHT-assoc, so
            // `a ?? b ?? c` is `a ?? (b ?? c)` — the chain shows as a Coalesce on the
            // right. A single `x ?? throw`/`x ?? const` is a lookup-with-default,
            // NOT a chain, so it stays misnamed-eligible.
            if ($coalesce->right instanceof Expr\BinaryOp\Coalesce) {
                return true;
            }
        }

        return false;
    }

    private function hasForeachFirstMatch(NodeFinder $finder, Node\Stmt\Class_ $class): bool
    {
        foreach ($finder->findInstanceOf($class->stmts, Node\Stmt\Foreach_::class) as $foreach) {
            foreach ($finder->findInstanceOf($foreach->stmts, Node\Stmt\If_::class) as $if) {
                if ($this->returnsOrThrows($if->stmts)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param  array<Node\Stmt>  $stmts
     */
    private function returnsOrThrows(array $stmts): bool
    {
        foreach ($stmts as $stmt) {
            if ($stmt instanceof Node\Stmt\Return_ && $stmt->expr !== null) {
                return true;
            }

            if ($stmt instanceof Node\Stmt\Throw_) {
                return true;
            }

            if ($stmt instanceof Node\Stmt\Expression && $stmt->expr instanceof Expr\Throw_) {
                return true;
            }
        }

        return false;
    }

    private function isCallish(Expr $expr): bool
    {
        return $expr instanceof Expr\MethodCall
            || $expr instanceof Expr\StaticCall
            || $expr instanceof Expr\FuncCall
            || $expr instanceof Expr\NullsafeMethodCall;
    }

    private function shortName(string $fqcn): string
    {
        $pos = strrpos($fqcn, '\\');

        return $pos === false ? $fqcn : substr($fqcn, $pos + 1);
    }

}
