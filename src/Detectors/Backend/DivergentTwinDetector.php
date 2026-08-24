<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Backend;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Ast\Support\ReachedUnit;
use JesseGall\CodeCommandments\Ast\Support\ResourceReach;
use JesseGall\CodeCommandments\Backend\Detector;
use JesseGall\CodeCommandments\Codebase as BaseCodebase;
use JesseGall\CodeCommandments\Detectors\RecurrenceDetector;
use JesseGall\CodeCommandments\Located;
use JesseGall\CodeCommandments\Sins\Backend\DivergentTwin;
use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Unpublished;

/**
 * Flags a method that does the same job as another and applies one STEP FEWER — its whole reach is the
 * other's, minus a verb. That asymmetry is what a refactor looks like when it landed in only one of two
 * places: both were right the day they were written, then one learned to check something and the other,
 * which nobody remembered existed, kept the old behaviour. The duplication is the cause; the missing step
 * is the bug, so the poorer path is what gets reported.
 */
final class DivergentTwinDetector implements Detector, RecurrenceDetector, Unpublished
{
    /**
     * How many resources the two must SHARE before they are one job rather than two small methods that
     * happen to nest.
     */
    private const int MIN_SHARED = 4;

    /**
     * How much of a shared core two paths WEARING THE SAME NAME need. Two methods called `store` are
     * already claimed to do one job, so the reach has less to prove on its own.
     */
    private const int MIN_SHARED_NAMESAKE = 3;

    /**
     * How many resources the POORER path may have that the richer lacks. Zero would demand a perfect
     * subset, and a path that also picked up one thing of its own is still a path missing a step.
     */
    private const int MAX_DIVERGE = 1;

    /**
     * How many steps the richer path may have on top before the two are simply different methods. A
     * forgotten step is one or two things; a dozen is another piece of work entirely.
     */
    private const int MAX_EXTRA = 2;

    /**
     * What share of the population may reach a resource before it stops telling us anything. A share,
     * not a count, so a fixture and a monorepo answer alike.
     */
    private const float MAX_SHARE = 0.05;


    /**
     * @var array<string, string>  location => the twin it diverges from
     */
    private array $twins = [];

    public function sin(): Sin
    {
        return new DivergentTwin();
    }

    public function groupKey(Located $finding, BaseCodebase $codebase): ?string
    {
        return $this->twins[$finding->location()] ?? null;
    }

    public function find(Codebase $codebase): array
    {
        $this->twins = [];

        $units = $this->units($codebase);
        $findings = [];

        foreach ($this->divergences($units, $codebase) as $poorer => $divergence) {
            $this->twins[$units[$poorer]->match->location()] = $divergence->describe();
            $findings[] = $units[$poorer]->match;
        }

        return $findings;
    }

    /**
     * Every method with a reach worth comparing, by scope.
     *
     * @return array<string, ReachedUnit>
     */
    private function units(Codebase $codebase): array
    {
        $scopes = ResourceReach::forCodebase($codebase)->scopes();

        $units = [];

        foreach ($codebase->whereMethodDeclaration()->get() as $method) {
            if (! $method->hasBody()) {
                continue; // an interface or abstract method names types but takes no steps
            }

            $steps = $scopes->rareOf($method->scope(), self::MAX_SHARE);

            if (count($steps) >= self::MIN_SHARED) {
                $units[$method->scope()] = new ReachedUnit($method, $steps);
            }
        }

        return $units;
    }

    /**
     * Does $callerScope CALL $callee? Then the two are not independent implementations of one job: either
     * the poorer routes through the funnel it appeared to bypass — the step it lacks being one call away
     * — or the richer is built ON the poorer, which is a part of it rather than its twin. Asked BOTH
     * ways, because either answer means the same thing.
     */
    private function calls(string $callerScope, ReachedUnit $callee, ResourceReach $reach): bool
    {
        $class = $callee->match->enclosingClassName();
        $method = $callee->match->methodName();

        if ($class === null || $method === null) {
            return false;
        }

        foreach ($reach->codebase()->index()->callersOf($class, $method) as $call) {
            if ($call->scope() === $callerScope) {
                return true;
            }
        }

        return false;
    }

    /**
     * Are these two the SAME method of one contract, implemented by different classes? Two `handle`s of a
     * middleware interface, two `compiled`s of a statement — siblings under one contract are meant to
     * differ, and the one doing less is answering for a different case, not skipping a step.
     */
    private function arePolymorphicSiblings(ReachedUnit $poorer, ReachedUnit $richer, ResourceReach $reach): bool
    {
        if ($poorer->match->methodName() !== $richer->match->methodName()) {
            return false;
        }

        $codebase = $reach->codebase();
        $method = $poorer->match->methodName();

        // A declared contract, or a convention wearing the same signature — a framework's `handle`
        // binds a middleware to a shape no interface states.
        return $poorer->match->signature() === $richer->match->signature()
            || ($codebase->overridesMethod($poorer->match->enclosingClassName(), $method)
                && $codebase->overridesMethod($richer->match->enclosingClassName(), $method));
    }

    /**
     * Each pair whose reaches differ by a step: the poorer path, the richer one it should be routed
     * through, and what it is missing. Paired through the resources themselves, so no two unrelated
     * methods are ever compared.
     *
     * @param  array<string, ReachedUnit>  $units
     * @return array<string, Divergence>  poorer scope => how it diverges
     */
    private function divergences(array $units, Codebase $codebase): array
    {
        $reach = ResourceReach::forCodebase($codebase);
        $holders = [];

        foreach ($units as $scope => $unit) {
            foreach ($unit->resources as $step) {
                $holders[$step][] = $scope;
            }
        }

        $divergences = [];

        foreach ($holders as $scopes) {
            foreach ($scopes as $index => $one) {
                foreach (array_slice($scopes, $index + 1) as $other) {
                    $divergence = $this->divergenceOf($units[$one], $units[$other], $one, $other, $reach);

                    if ($divergence !== null) {
                        // Keyed by the POORER path: it is one sin however many twins reveal it, and the
                        // pair is reached once per resource the two share. A twin that shows a skipped
                        // CHECK outranks one that merely shows extra work.
                        $standing = $divergences[$divergence->poorer] ?? null;

                        if ($standing === null || ($divergence->skipsACheck() && ! $standing->skipsACheck())) {
                            $divergences[$divergence->poorer] = $divergence;
                        }
                    }
                }
            }
        }

        return $divergences;
    }

    /**
     * Is one of these two the other MINUS a step? Every resource the poorer reaches must be the richer's,
     * the shared core must be substantial, and what is missing must include a VERB — a step that DOES
     * something. A path missing only a named type is working on less, not skipping a check.
     *
     */
    private function divergenceOf(ReachedUnit $first, ReachedUnit $second, string $one, string $other, ResourceReach $reach): ?Divergence
    {
        [$poorer, $richer, $poorerScope, $richerScope] = $first->count() <= $second->count()
            ? [$first, $second, $one, $other]
            : [$second, $first, $other, $one];

        $missing = $poorer->missingFrom($richer);

        if ($missing === [] || count($missing) > self::MAX_EXTRA) {
            return null;
        }

        if (count($richer->missingFrom($poorer)) > self::MAX_DIVERGE) {
            return null; // diverging BOTH ways in earnest: two different methods, not one minus a step
        }

        if ($this->arePolymorphicSiblings($poorer, $richer, $reach)) {
            return null;
        }

        if ($this->calls($poorerScope, $richer, $reach) || $this->calls($richerScope, $poorer, $reach)) {
            return null;
        }

        $namesakes = $poorer->match->methodName() === $richer->match->methodName();

        if (count($poorer->sharedWith($richer)) < ($namesakes ? self::MIN_SHARED_NAMESAKE : self::MIN_SHARED)) {
            return null;
        }

        $verbs = array_filter($missing, static fn (string $step): bool => ! $reach->isType($step));

        if ($verbs === []) {
            return null; // missing only a named type is working on less, not skipping a step
        }

        $guards = array_values(array_filter(
            $missing,
            static fn (string $step): bool => $reach->isGuardedIn($richerScope, $step),
        ));

        return new Divergence($poorerScope, $richerScope, $missing, $guards);
    }
}
