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
        $findings = [];

        foreach ($components->components() as $component) {
            $boundaries = [];

            foreach ($component->template->descendants() as $element) {
                if ($element->depth() <= self::MAX_DEPTH || $element->height() - 1 <= self::MAX_REMAINING) {
                    continue; // not deep enough, or nothing substantial below it
                }

                // From the too-deep element, look back up for the natural boundary.
                $boundary = Boundary::at($element, $component)->root();

                if ($boundary->valid()) {
                    $boundaries[spl_object_id($boundary->node)] ??= $boundary->match();
                }
            }

            $findings = array_merge($findings, array_values($boundaries));
        }

        return $findings;
    }
}
