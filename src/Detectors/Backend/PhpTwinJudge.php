<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Backend;

use JesseGall\CodeCommandments\Ast\NodeMatch;
use JesseGall\CodeCommandments\Ast\Support\ReachedUnit;
use JesseGall\CodeCommandments\Ast\Support\ResourceReach;
use JesseGall\CodeCommandments\Ast\TypeName;
use JesseGall\CodeCommandments\Detectors\TwinJudge;

/**
 * What PHP answers for {@see \JesseGall\CodeCommandments\Detectors\DivergentTwins}: units are methods,
 * keyed by `Class::method`, over the codebase's {@see ResourceReach}.
 */
final readonly class PhpTwinJudge implements TwinJudge
{
    public function __construct(private ResourceReach $reach) {}

    public function isType(string $resource): bool
    {
        return $this->reach->isType($resource);
    }

    /**
     * A DECLARED contract only. Matching signatures alone exempted far too much — `rows(string): array` is
     * a shape two unrelated classes reach by accident, and calling that a contract excused the very
     * duplication being looked for.
     *
     * @param  ReachedUnit<NodeMatch>  $poorer
     * @param  ReachedUnit<NodeMatch>  $richer
     */
    public function arePolymorphicSiblings(ReachedUnit $poorer, ReachedUnit $richer): bool
    {
        $method = $poorer->match->methodName();

        if ($method !== $richer->match->methodName()) {
            return false;
        }

        $codebase = $this->reach->codebase();

        return $codebase->overridesMethod($poorer->match->enclosingClassName(), $method)
            && $codebase->overridesMethod($richer->match->enclosingClassName(), $method);
    }

    /**
     * A method handing back an object and one handing back an array are not one job however alike their
     * insides read, and neither is a command beside a question. Judged only where both DECLARE a result.
     *
     * @param  ReachedUnit<NodeMatch>  $poorer
     * @param  ReachedUnit<NodeMatch>  $richer
     */
    public function resultsAreIncomparable(ReachedUnit $poorer, ReachedUnit $richer): bool
    {
        $one = $poorer->match->returnTypeName();
        $other = $richer->match->returnTypeName();

        if ($one === '' || $other === '' || $one === $other) {
            return false;
        }

        $codebase = $this->reach->codebase();
        $oneIsObject = $codebase->declarationMatch($one) !== null;
        $otherIsObject = $codebase->declarationMatch($other) !== null;

        if ($oneIsObject !== $otherIsObject) {
            return true; // an object beside a builtin
        }

        if ($oneIsObject) {
            return ! $codebase->isA($one, $other) && ! $codebase->isA($other, $one);
        }

        // Two builtins that cannot hold the same value are not one job: an array of derived names beside a
        // resolved path-or-false compose alike and answer differently.
        return ! TypeName::overlaps($one, $other);
    }

    /**
     * @param  ReachedUnit<NodeMatch>  $unit
     */
    public function callersOf(ReachedUnit $unit): array
    {
        $class = $unit->match->enclosingClassName();
        $method = $unit->match->methodName();

        if ($class === null || $method === null) {
            return [];
        }

        $callers = [];

        foreach ($this->reach->codebase()->index()->callersOf($class, $method) as $call) {
            $callers[$call->scope()] = true;
        }

        return array_keys($callers);
    }
}
