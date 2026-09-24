<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\CSharp;

use JesseGall\CodeCommandments\Cs\Codebase;
use JesseGall\CodeCommandments\Cs\NodeMatch;
use JesseGall\CodeCommandments\CSharp\Detector;
use JesseGall\CodeCommandments\Sins\CSharp\DeNulledFinder;
use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\WholeTree;

/**
 * A finder returning a nullable object whose every caller, two at least, asserts it is there — the twin of the
 * backend's de-nulled-finder rule. In C# a `T?` is the honest maybe, so a caller branching on the absence is not
 * this; one forcing it with `!` or `?? throw` says the absence cannot happen. A method whose nullable return an
 * interface or a base class declares is exempt: that signature is not the type's to narrow.
 */
final class DeNulledFinderDetector implements Detector, WholeTree
{
    /**
     * The result must reach this many call sites before re-deciding its absence at each is a pattern.
     */
    private const int TRAVELS = 2;

    public function sin(): Sin
    {
        return new DeNulledFinder();
    }

    public function find(Codebase $codebase): array
    {
        return $codebase
            ->whereMethodDeclaration()
            ->reject(static fn (NodeMatch $match): bool => $match->node->inherited)
            ->where(static fn (NodeMatch $match): bool => $match->node->declaredType()?->nullable === true)
            ->where(static fn (NodeMatch $match): bool => $match->node->declaredType()?->isValueType === false)
            ->reject(static fn (NodeMatch $match): bool => rtrim((string) $match->node->declaredType()?->name, '?') === 'global::System.String')
            ->where(fn (NodeMatch $match) => $this->isDeNulledByEveryCaller($match, $codebase))
            ->get();
    }

    /**
     * Is the finder's result asserted present at every call site in the product, and does it reach at least
     * {@see TRAVELS} of them? A test knows the case it built, so its `!` says nothing about a miss in production.
     */
    private function isDeNulledByEveryCaller(NodeMatch $finder, Codebase $codebase): bool
    {
        $callers = array_filter($codebase->callersOf($finder->node), static fn (NodeMatch $call): bool => ! $call->module->isTest());

        return count($callers) >= self::TRAVELS && array_all($callers, static fn (NodeMatch $call): bool => $call->resultIsAssertedPresent());
    }
}
