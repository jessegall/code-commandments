<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Backend;

use JesseGall\CodeCommandments\Ast\AstNode;
use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Ast\NodeMatch;
use JesseGall\CodeCommandments\Ast\Support\ReachedUnit;
use JesseGall\CodeCommandments\Ast\Support\ResourceReach;
use JesseGall\CodeCommandments\Backend\Detector;
use JesseGall\CodeCommandments\Detectors\Divergence;
use JesseGall\CodeCommandments\Detectors\DivergentTwins;
use JesseGall\CodeCommandments\Detectors\RecurrenceDetector;
use JesseGall\CodeCommandments\Sins\Backend\DivergentTwin;
use JesseGall\CodeCommandments\Sins\Sin;

/**
 * Flags a method that does the same job as another and does STRICTLY LESS of it. Sameness is
 * established first, and only on VERBS — what the two bodies do to the world — because two methods
 * handling the same subject are not thereby doing the same thing with it; only then is the poorer one
 * asked what it lacks. That asymmetry is what a refactor looks like when it landed in one of two places
 * that should have been one.
 */
final class DivergentTwinDetector implements Detector, RecurrenceDetector
{
    use GroupsByFingerprint;

    /**
     * @var \WeakMap<Codebase, list<Divergence>>|null  the reading for a codebase, worked out once
     */
    private ?\WeakMap $memo = null;

    public function sin(): Sin
    {
        return new DivergentTwin();
    }

    protected function fingerprint(NodeMatch $finding, Codebase $codebase): ?string
    {
        return DivergentTwins::pairOf($this->readingOf($codebase), $finding->scope());
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

        return $this->memo[$codebase] ??= new DivergentTwins(new PhpTwinJudge(ResourceReach::forCodebase($codebase)))->divergences($this->units($codebase));
    }

    /**
     * Every method whose body is worth comparing, by scope.
     *
     * @return array<string, ReachedUnit<NodeMatch>>
     */
    private function units(Codebase $codebase): array
    {
        $scopes = ResourceReach::forCodebase($codebase)->scopes();

        $declarations = $codebase->whereMethodDeclaration()
            ->reject(static fn (AstNode $node): bool => ! $node->hasBody())
            ->get();

        $units = [];

        foreach ($declarations as $method) {
            $steps = $scopes->rareOf($method->scope(), DivergentTwins::MAX_SHARE);

            if (count($steps) >= DivergentTwins::MIN_SHARED) {
                $units[$method->scope()] = new ReachedUnit($method, $steps);
            }
        }

        return $units;
    }
}
