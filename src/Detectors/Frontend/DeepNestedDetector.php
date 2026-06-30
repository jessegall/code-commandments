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
 * A template nested far too deep — an element {@see MIN_DEPTH}+ levels in that still
 * has {@see MIN_REMAINING}+ levels of markup beneath it. That depth is unreadable and
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
    private const int MAX_DEPTH = 8; // nested DEEPER than this is too deep

    private const int MAX_REMAINING = 3; // with MORE levels than this still below it

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

        foreach ($components->whereElement()->nestedDeeperThan(self::MAX_DEPTH, self::MAX_REMAINING)->get() as $element) {
            // From each too-deep element climb to its natural boundary; dedup the shared ones.
            $boundary = Boundary::at($element->node, $element->sfc)->root();

            if ($boundary->valid()) {
                $boundaries[spl_object_id($boundary->node)] ??= $boundary->match();
            }
        }

        return array_values($boundaries);
    }
}
