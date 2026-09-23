<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors;

use JesseGall\CodeCommandments\Ast\Support\ReachedUnit;
use JesseGall\CodeCommandments\Ast\Support\ReachPairs;

/**
 * Which units do the same job as another and strictly less of it — the one reading behind every engine's
 * divergent-twin rule. Sameness is established first, and only on VERBS — what two bodies do to the world —
 * because two units handling the same subject are not thereby doing the same thing with it; only then is
 * the poorer one asked what it lacks. What differs by language is asked of the engine's {@see TwinJudge}.
 */
final class DivergentTwins
{
    /**
     * How many resources two paths must share before they are worth comparing at all.
     */
    public const int MIN_SHARED = 4;

    /**
     * What share of the population may reach a resource before it stops telling us anything. Measured, not
     * guessed: on a real tree the verbs that name a mechanism sit at or under half a percent of scopes
     * (`rename` 0.01%, `realpath` 0.08%, `json_decode` 0.35%), while the transformation idioms every program
     * repeats sit well above one (`array_map` 4%, `sprintf` 3.1%, `is_string` 1.4%). A share, not a count,
     * so a fixture and a monorepo answer alike.
     */
    public const float MAX_SHARE = 0.01;

    /**
     * How many of the SHARED resources must be verbs. This is what establishes sameness: a core of types
     * says the two work on one subject, which a module's every method does; a core of verbs says they do one
     * job.
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
     * How far a call is followed when asking whether one path already routes through another. One hop is
     * not enough: a funnel is commonly reached through a small private helper.
     */
    private const int CALL_DEPTH = 2;

    /**
     * @var array<string, list<string>>  unit key => the units that call it
     */
    private array $callers = [];

    public function __construct(private readonly TwinJudge $judge) {}

    /**
     * Each pair of $units found to be one job done twice, where one does less of it — one finding per
     * divergent path, the strongest pair claiming it.
     *
     * @param  array<string, ReachedUnit>  $units  by the key the judge names callers with
     * @return list<Divergence>
     */
    public function divergences(array $units): array
    {
        $divergences = [];
        $claimed = [];

        foreach (ReachPairs::sharing($units, self::MIN_SHARED) as [$one, $other]) {
            $divergence = $this->divergenceOf($units[$one], $units[$other], $one, $other);

            if ($divergence !== null && ! isset($claimed[$divergence->poorer])) {
                $claimed[$divergence->poorer] = true;
                $divergences[] = $divergence;
            }
        }

        return $divergences;
    }

    /**
     * The pair $key belongs to among $divergences, named the same way from either side so both members
     * bucket together — a fingerprint, not a sentence.
     *
     * @param  list<Divergence>  $divergences
     */
    public static function pairOf(array $divergences, string $key): ?string
    {
        foreach ($divergences as $divergence) {
            if ($divergence->poorer === $key || $divergence->richer === $key) {
                return $divergence->poorer < $divergence->richer
                    ? "{$divergence->poorer}|{$divergence->richer}"
                    : "{$divergence->richer}|{$divergence->poorer}";
            }
        }

        return null;
    }

    /**
     * Is one of these two the other doing strictly less?
     */
    private function divergenceOf(ReachedUnit $first, ReachedUnit $second, string $one, string $other): ?Divergence
    {
        [$poorer, $richer, $poorerKey, $richerKey] = $first->count() <= $second->count()
            ? [$first, $second, $one, $other]
            : [$second, $first, $other, $one];

        $core = $poorer->sharedWith($richer);
        $missing = $poorer->missingFrom($richer);

        if (! $this->isOneJob($core)) {
            return null;
        }

        if ($missing === [] || count($missing) > self::MAX_EXTRA) {
            return null;
        }

        if (count($richer->missingFrom($poorer)) > self::MAX_DIVERGE) {
            return null; // diverging BOTH ways in earnest: two different methods, not one doing less
        }

        if ($this->verbsIn($missing) === []) {
            return null; // missing only a named type is working on less, not skipping a step
        }

        if ($this->areAlternatives($poorer, $richer, $poorerKey, $richerKey)) {
            return null;
        }

        return new Divergence($poorerKey, $richerKey, $missing);
    }

    /**
     * Do these two do ONE JOB — is their shared core carried by verbs rather than by the subject they both
     * happen to handle?
     *
     * @param  list<string>  $core
     */
    private function isOneJob(array $core): bool
    {
        return count($core) >= self::MIN_SHARED && count($this->verbsIn($core)) >= self::MIN_CORE_VERBS;
    }

    /**
     * @param  list<string>  $resources
     * @return list<string>
     */
    private function verbsIn(array $resources): array
    {
        return array_values(array_filter($resources, fn (string $resource): bool => ! $this->judge->isType($resource)));
    }

    /**
     * Are these two anything OTHER than independent implementations of one job — siblings under one
     * contract, alternatives a third unit chooses between, one built on the other, or two that cannot be
     * producing the same thing at all?
     */
    private function areAlternatives(ReachedUnit $poorer, ReachedUnit $richer, string $poorerKey, string $richerKey): bool
    {
        return $this->judge->arePolymorphicSiblings($poorer, $richer)
            || $this->judge->resultsAreIncomparable($poorer, $richer)
            || $this->routesThrough($poorerKey, $richer, $richerKey)
            || $this->routesThrough($richerKey, $poorer, $poorerKey)
            || $this->shareACaller($poorer, $richer, $poorerKey, $richerKey);
    }

    /**
     * Does $callerKey reach $callee by calling it, directly or through a helper? Then the two are not
     * independent: the step the poorer appears to lack is a call or two away.
     */
    private function routesThrough(string $callerKey, ReachedUnit $callee, string $calleeKey): bool
    {
        $frontier = [$callerKey => true];

        for ($hop = 0; $hop < self::CALL_DEPTH; $hop++) {
            foreach ($this->callersOf($callee, $calleeKey) as $caller) {
                if (isset($frontier[$caller])) {
                    return true;
                }
            }

            $frontier = $this->callersOfKeys(array_keys($frontier));
        }

        return false;
    }

    /**
     * Is there a unit that calls BOTH? Two paths a third chooses between are alternatives, and the one
     * doing less is answering for its own case, not forgetting the other's.
     */
    private function shareACaller(ReachedUnit $poorer, ReachedUnit $richer, string $poorerKey, string $richerKey): bool
    {
        return array_intersect($this->callersOf($poorer, $poorerKey), $this->callersOf($richer, $richerKey)) !== [];
    }

    /**
     * @return list<string>
     */
    private function callersOf(ReachedUnit $unit, string $key): array
    {
        return $this->callers[$key] ??= $this->judge->callersOf($unit);
    }

    /**
     * Who calls any of $keys — one hop out from a frontier.
     *
     * @param  list<string>  $keys
     * @return array<string, true>
     */
    private function callersOfKeys(array $keys): array
    {
        $next = [];

        foreach ($keys as $key) {
            foreach ($this->callers[$key] ?? [] as $caller) {
                $next[$caller] = true;
            }
        }

        return $next;
    }
}
