<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Backend;

use JesseGall\CodeCommandments\Ast\AstNode;
use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Ast\Support\ReachedUnit;
use JesseGall\CodeCommandments\Ast\Support\ReachPairs;
use JesseGall\CodeCommandments\Ast\Support\ResourceReach;
use JesseGall\CodeCommandments\Ast\TypeName;
use JesseGall\CodeCommandments\Backend\Detector;
use JesseGall\CodeCommandments\Codebase as BaseCodebase;
use JesseGall\CodeCommandments\Detectors\RecurrenceDetector;
use JesseGall\CodeCommandments\Located;
use JesseGall\CodeCommandments\Sins\Backend\DivergentTwin;
use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Unpublished;

/**
 * Flags a method that does the same job as another and does STRICTLY LESS of it. Sameness is
 * established first, and only on VERBS — what the two bodies do to the world — because two methods
 * handling the same subject are not thereby doing the same thing with it; only then is the poorer one
 * asked what it lacks. That asymmetry is what a refactor looks like when it landed in one of two places
 * that should have been one.
 */
final class DivergentTwinDetector implements Detector, RecurrenceDetector, Unpublished
{
    /**
     * How many resources two paths must share before they are worth comparing at all.
     */
    private const int MIN_SHARED = 4;

    /**
     * How many of the SHARED resources must be verbs. This is what establishes sameness: a core of
     * types says the two work on one subject, which a module's every method does; a core of verbs says
     * they do one job.
     */
    private const int MIN_CORE_VERBS = 2;

    /**
     * How many steps the richer path may have on top before the two are simply different methods.
     */
    private const int MAX_EXTRA = 2;

    /**
     * How many resources the POORER path may have that the richer lacks.
     */
    private const int MAX_DIVERGE = 1;

    /**
     * What share of the population may reach a resource before it stops telling us anything. A share,
     * not a count, so a fixture and a monorepo answer alike.
     */
    private const float MAX_SHARE = 0.05;

    /**
     * How far a call is followed when asking whether one path already routes through another. One hop
     * is not enough: a funnel is commonly reached through a small private helper.
     */
    private const int CALL_DEPTH = 2;

    /**
     * @var array<string, list<string>>  scope => the scopes that call it, kept for one run
     */
    private array $callers = [];

    /**
     * @var \WeakMap<Codebase, list<Divergence>>|null  the reading for a codebase, worked out once
     */
    private ?\WeakMap $memo = null;

    public function sin(): Sin
    {
        return new DivergentTwin();
    }

    public function groupKey(Located $finding, BaseCodebase $codebase): ?string
    {
        return $finding instanceof \JesseGall\CodeCommandments\Ast\NodeMatch && $codebase instanceof Codebase
            ? $this->twinOf($finding, $codebase)
            : null;
    }

    public function find(Codebase $codebase): array
    {
        $units = $this->units($codebase);
        $findings = [];

        foreach ($this->readingOf($codebase) as $divergence) {
            // BOTH members: the duplication is the sin and it is theirs jointly, so a reader is shown
            // the twin rather than told a method "does less" without being told less than what.
            $findings[] = $units[$divergence->poorer]->match;
            $findings[] = $units[$divergence->richer]->match;
        }

        return $findings;
    }

    /**
     * What this codebase says, worked out once. Derived wholly from the codebase rather than left over
     * from a `find()` that may never have run, so a key is the same whoever asks and in whatever order.
     *
     * @return list<Divergence>
     */
    private function readingOf(Codebase $codebase): array
    {
        $this->memo ??= new \WeakMap();

        return $this->memo[$codebase] ??= $this->divergences($this->units($codebase), $codebase);
    }

    /**
     * The pair this finding belongs to, named the same way from either side so both members bucket
     * together — a fingerprint, not a sentence.
     */
    private function twinOf(\JesseGall\CodeCommandments\Ast\NodeMatch $finding, Codebase $codebase): ?string
    {
        foreach ($this->readingOf($codebase) as $divergence) {
            if ($divergence->poorer === $finding->scope() || $divergence->richer === $finding->scope()) {
                return $divergence->poorer < $divergence->richer
                    ? "{$divergence->poorer}|{$divergence->richer}"
                    : "{$divergence->richer}|{$divergence->poorer}";
            }
        }

        return null;
    }

    /**
     * Every method whose body is worth comparing, by scope.
     *
     * @return array<string, ReachedUnit>
     */
    private function units(Codebase $codebase): array
    {
        $scopes = ResourceReach::forCodebase($codebase)->scopes();

        $declarations = $codebase->whereMethodDeclaration()
            ->reject(static fn (AstNode $node): bool => ! $node->hasBody())
            ->get();

        $units = [];

        foreach ($declarations as $method) {
            $steps = $scopes->rareOf($method->scope(), self::MAX_SHARE);

            if (count($steps) >= self::MIN_SHARED) {
                $units[$method->scope()] = new ReachedUnit($method, $steps);
            }
        }

        return $units;
    }

    /**
     * Each pair found to be one job done twice, where one does less of it.
     *
     * @param  array<string, ReachedUnit>  $units
     * @return list<Divergence>
     */
    private function divergences(array $units, Codebase $codebase): array
    {
        $this->callers = [];
        $reach = ResourceReach::forCodebase($codebase);
        $divergences = [];
        $claimed = [];

        foreach (ReachPairs::sharing($units, self::MIN_SHARED) as [$one, $other]) {
            $divergence = $this->divergenceOf($units[$one], $units[$other], $one, $other, $reach);

            // One finding per divergent path, and the strongest pair claims it — the pairs arrive
            // most-alike first, so the first to claim a path is the clearest twin it has.
            if ($divergence !== null && ! isset($claimed[$divergence->poorer])) {
                $claimed[$divergence->poorer] = true;
                $divergences[] = $divergence;
            }
        }

        return $divergences;
    }

    /**
     * Is one of these two the other doing strictly less?
     */
    private function divergenceOf(ReachedUnit $first, ReachedUnit $second, string $one, string $other, ResourceReach $reach): ?Divergence
    {
        [$poorer, $richer, $poorerScope, $richerScope] = $first->count() <= $second->count()
            ? [$first, $second, $one, $other]
            : [$second, $first, $other, $one];

        $core = $poorer->sharedWith($richer);
        $missing = $poorer->missingFrom($richer);

        if (! $this->isOneJob($core, $reach)) {
            return null;
        }

        if ($missing === [] || count($missing) > self::MAX_EXTRA) {
            return null;
        }

        if (count($richer->missingFrom($poorer)) > self::MAX_DIVERGE) {
            return null; // diverging BOTH ways in earnest: two different methods, not one doing less
        }

        if ($this->verbsIn($missing, $reach) === []) {
            return null; // missing only a named type is working on less, not skipping a step
        }

        if ($this->areAlternatives($poorer, $richer, $poorerScope, $richerScope, $reach)) {
            return null;
        }

        $guards = array_values(array_filter(
            $missing,
            static fn (string $step): bool => $reach->isGuardedIn($richerScope, $step),
        ));

        return new Divergence($poorerScope, $richerScope, $missing, $guards);
    }

    /**
     * Do these two do ONE JOB — is their shared core carried by verbs rather than by the subject they
     * both happen to handle? A module's every method names the module's own types; only what the bodies
     * DO can say they are the same mechanism.
     *
     * @param  list<string>  $core
     */
    private function isOneJob(array $core, ResourceReach $reach): bool
    {
        return count($core) >= self::MIN_SHARED
            && count($this->verbsIn($core, $reach)) >= self::MIN_CORE_VERBS;
    }

    /**
     * @param  list<string>  $resources
     * @return list<string>
     */
    private function verbsIn(array $resources, ResourceReach $reach): array
    {
        return array_values(array_filter($resources, static fn (string $r): bool => ! $reach->isType($r)));
    }

    /**
     * Are these two anything OTHER than independent implementations of one job — siblings under one
     * contract, alternatives a third method chooses between, one built on the other, or two that cannot
     * be producing the same thing at all?
     */
    private function areAlternatives(ReachedUnit $poorer, ReachedUnit $richer, string $poorerScope, string $richerScope, ResourceReach $reach): bool
    {
        return $this->arePolymorphicSiblings($poorer, $richer, $reach)
            || $this->resultsAreIncomparable($poorer, $richer, $reach)
            || $this->routesThrough($poorerScope, $richer, $reach)
            || $this->routesThrough($richerScope, $poorer, $reach)
            || $this->shareACaller($poorer, $richer, $reach);
    }

    /**
     * Are these two the SAME method of one contract, implemented by different classes? Siblings under a
     * contract are MEANT to differ, and the one doing less is answering for a different case.
     */
    private function arePolymorphicSiblings(ReachedUnit $poorer, ReachedUnit $richer, ResourceReach $reach): bool
    {
        if ($poorer->match->methodName() !== $richer->match->methodName()) {
            return false;
        }

        $codebase = $reach->codebase();
        $method = $poorer->match->methodName();

        return $poorer->match->signature() === $richer->match->signature()
            || ($codebase->overridesMethod($poorer->match->enclosingClassName(), $method)
                && $codebase->overridesMethod($richer->match->enclosingClassName(), $method));
    }

    /**
     * Could these two even be producing the same thing? A method handing back an object and one handing
     * back an array are not one job however alike their insides read, and neither is a command beside a
     * question. Judged only where both DECLARE a result — an undeclared one says nothing either way.
     */
    private function resultsAreIncomparable(ReachedUnit $poorer, ReachedUnit $richer, ResourceReach $reach): bool
    {
        $one = $poorer->match->returnTypeName();
        $other = $richer->match->returnTypeName();

        if ($one === '' || $other === '' || $one === $other) {
            return false;
        }

        $codebase = $reach->codebase();
        $oneIsObject = $codebase->declarationMatch($one) !== null;
        $otherIsObject = $codebase->declarationMatch($other) !== null;

        if ($oneIsObject !== $otherIsObject) {
            return true; // an object beside a builtin
        }

        if ($oneIsObject) {
            return ! $codebase->isA($one, $other) && ! $codebase->isA($other, $one);
        }

        // Two builtins that cannot hold the same value are not one job: an array of derived names
        // beside a resolved path-or-false compose alike and answer differently.
        return ! TypeName::overlaps($one, $other);
    }

    /**
     * Does $callerScope reach $callee by calling it, directly or through a helper? Then the two are not
     * independent: the step the poorer appears to lack is a call or two away, and reaching a thing
     * through what you call is reaching it.
     */
    private function routesThrough(string $callerScope, ReachedUnit $callee, ResourceReach $reach): bool
    {
        $frontier = [$callerScope => true];

        for ($hop = 0; $hop < self::CALL_DEPTH; $hop++) {
            foreach ($this->callersOf($callee, $reach) as $caller) {
                if (isset($frontier[$caller])) {
                    return true;
                }
            }

            $frontier = $this->callersOfScopes(array_keys($frontier), $reach);
        }

        return false;
    }

    /**
     * Is there a method that calls BOTH? Two paths a third chooses between are ALTERNATIVES — the
     * `except` beside the `only`, the cursor paginator beside the length-aware one — and the one doing
     * less is answering for its own case, not forgetting the other's.
     */
    private function shareACaller(ReachedUnit $poorer, ReachedUnit $richer, ResourceReach $reach): bool
    {
        return array_intersect($this->callersOf($poorer, $reach), $this->callersOf($richer, $reach)) !== [];
    }

    /**
     * The scopes that call $unit.
     *
     * @return list<string>
     */
    private function callersOf(ReachedUnit $unit, ResourceReach $reach): array
    {
        $scope = $unit->match->scope();

        if (isset($this->callers[$scope])) {
            return $this->callers[$scope];
        }

        $class = $unit->match->enclosingClassName();
        $method = $unit->match->methodName();
        $callers = [];

        if ($class !== null && $method !== null) {
            foreach ($reach->codebase()->index()->callersOf($class, $method) as $call) {
                $callers[$call->scope()] = true;
            }
        }

        return $this->callers[$scope] = array_keys($callers);
    }

    /**
     * Who calls any of $scopes — one hop out from a frontier.
     *
     * @param  list<string>  $scopes
     * @return array<string, true>
     */
    private function callersOfScopes(array $scopes, ResourceReach $reach): array
    {
        $next = [];

        foreach ($scopes as $scope) {
            foreach ($this->callers[$scope] ?? [] as $caller) {
                $next[$caller] = true;
            }
        }

        return $next;
    }
}
