<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Python;

use JesseGall\CodeCommandments\Ast\Support\ReachedUnit;
use JesseGall\CodeCommandments\Codebase as BaseCodebase;
use JesseGall\CodeCommandments\Detectors\Divergence;
use JesseGall\CodeCommandments\Detectors\DivergentTwins;
use JesseGall\CodeCommandments\Detectors\RecurrenceDetector;
use JesseGall\CodeCommandments\Located;
use JesseGall\CodeCommandments\Py\Codebase;
use JesseGall\CodeCommandments\Py\Node\FunctionDef;
use JesseGall\CodeCommandments\Py\NodeMatch;
use JesseGall\CodeCommandments\Python\Detector;
use JesseGall\CodeCommandments\Sins\Python\DivergentTwin;
use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\WholeTree;

/**
 * A function that does the same job as another and strictly less of it — the twin of the backend's
 * {@see \JesseGall\CodeCommandments\Detectors\Backend\DivergentTwinDetector}, read by the shared
 * {@see DivergentTwins} over what each function reaches. Both members are flagged: the duplication is theirs
 * jointly.
 */
final class DivergentTwinDetector implements Detector, RecurrenceDetector, WholeTree
{
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
        if (! $finding instanceof NodeMatch || ! $finding->node instanceof FunctionDef || ! $codebase instanceof Codebase) {
            return null;
        }

        return DivergentTwins::pairOf($this->readingOf($codebase), $codebase->index()->declarationOf($finding->node));
    }

    public function find(Codebase $codebase): array
    {
        $units = $this->units($codebase);
        $findings = [];

        foreach ($this->readingOf($codebase) as $divergence) {
            $findings[$divergence->poorer] = $units[$divergence->poorer]->match;
            $findings[$divergence->richer] = $units[$divergence->richer]->match;
        }

        return array_values($findings);
    }

    /**
     * @return list<Divergence>
     */
    private function readingOf(Codebase $codebase): array
    {
        $this->memo ??= new \WeakMap();

        return $this->memo[$codebase] ??= new DivergentTwins(new PythonTwinJudge($codebase))->divergences($this->units($codebase));
    }

    /**
     * Every function reaching enough rare resources to be compared, by where it is declared.
     *
     * @return array<string, ReachedUnit<NodeMatch>>
     */
    private function units(Codebase $codebase): array
    {
        $functions = $codebase->resourceReach()->functions();
        $units = [];

        foreach ($codebase->whereFunction()->get() as $match) {
            if (! $match->node instanceof FunctionDef) {
                continue;
            }

            $key = $codebase->index()->declarationOf($match->node);
            $steps = $functions->rareOf($key, DivergentTwins::MAX_SHARE);

            if (count($steps) >= DivergentTwins::MIN_SHARED) {
                $units[$key] = new ReachedUnit($match, $steps);
            }
        }

        return $units;
    }
}
