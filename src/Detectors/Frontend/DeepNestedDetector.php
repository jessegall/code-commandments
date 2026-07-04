<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Frontend;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Sins\Frontend\DeepNested;
use JesseGall\CodeCommandments\Detectors\Frontend\Config\DeepNestedConfig;
use JesseGall\CodeCommandments\Detectors\Repentable;
use JesseGall\CodeCommandments\Scribes\Frontend\ExtractComponentScribe;
use JesseGall\CodeCommandments\Vue\Boundary;
use JesseGall\CodeCommandments\Vue\Codebase;
use JesseGall\CodeCommandments\Frontend\Detector;

/**
 * A template nested far too deep; identifies the natural extraction boundary by climbing
 * from the deep element up the single-child wrapper stack for a coherent unit.
 */
final class DeepNestedDetector implements Detector, Repentable
{
    use DeepNestedConfig;

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
