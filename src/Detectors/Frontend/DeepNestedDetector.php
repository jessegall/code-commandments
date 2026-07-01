<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Frontend;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Sins\Frontend\DeepNested;
use JesseGall\CodeCommandments\Detectors\Repentable;
use JesseGall\CodeCommandments\Scribes\Frontend\ExtractComponentScribe;
use JesseGall\CodeCommandments\Vue\Boundary;
use JesseGall\CodeCommandments\Vue\Codebase;
use JesseGall\CodeCommandments\Vue\Detector;

/**
 * A template nested far too deep — an element {@see $maxDepth}+ levels in that still
 * has {@see $maxRemaining}+ levels of markup beneath it. That depth is unreadable and
 * a sign a whole sub-tree wants to be its own component. Points at vue-components,
 * fixed by the same extract scribe.
 *
 * The finding is NOT the arbitrary deep element: from it we LOOK BACK up the tree for
 * the natural starting point — the top of the single-child wrapper stack the deep
 * branch sits in ({@see boundary}) — so the extracted component is a coherent unit and
 * lifting it flattens the host meaningfully. Depth and subtree height are read off the
 * element tree, never a heuristic on tag names.
 */
final class DeepNestedDetector implements Detector, Repentable
{
    private int $maxDepth = 8; // nested DEEPER than this is too deep

    private int $maxRemaining = 3; // with MORE levels than this still below it

    /** Tune how deep is "too deep" — an element nested deeper than this counts. */
    public function maxDepth(int $levels): static
    {
        $this->maxDepth = $levels;

        return $this;
    }

    /** Tune how much subtree beneath still makes it worth extracting. */
    public function maxRemaining(int $levels): static
    {
        $this->maxRemaining = $levels;

        return $this;
    }

    public function sin(): Sin
    {
        return new DeepNested();
    }

    public function scribe(): ExtractComponentScribe
    {
        return ExtractComponentScribe::forNesting();
    }

    public function find(Codebase $components): array
    {
        $boundaries = [];

        foreach ($components->whereElement()->nestedDeeperThan($this->maxDepth, $this->maxRemaining)->get() as $element) {
            // From each too-deep element climb to its natural boundary; dedup the shared ones.
            $boundary = Boundary::at($element->node, $element->sfc)->root();

            if ($boundary->valid()) {
                $boundaries[spl_object_id($boundary->node)] ??= $boundary->match();
            }
        }

        return array_values($boundaries);
    }
}
