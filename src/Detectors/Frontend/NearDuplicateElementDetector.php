<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Frontend;

use JesseGall\CodeCommandments\Codebase as BaseCodebase;
use JesseGall\CodeCommandments\Detectors\BucketsByGroupKey;
use JesseGall\CodeCommandments\Detectors\RecurrenceDetector;
use JesseGall\CodeCommandments\Frontend\Detector;
use JesseGall\CodeCommandments\Located;
use JesseGall\CodeCommandments\Sins\Frontend\NearDuplicateElement;
use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Vue\Codebase;
use JesseGall\CodeCommandments\Vue\Element;
use JesseGall\CodeCommandments\Vue\ElementMatch;

/**
 * Template blocks with one SKELETON that bind different data — the same tags, attribute names and
 * nesting, with the values and text left out — within a template, across components, or as two
 * components' whole templates. The value-blind twin of {@see DuplicateElementDetector}, which owns
 * every block that has a byte-identical copy; only the outermost repeated skeleton is reported.
 */
final class NearDuplicateElementDetector implements Detector, RecurrenceDetector
{
    use BucketsByGroupKey;

    /**
     * Minimum elements in a block — higher than the exact detector's floor, since a skeleton with the
     * values erased collides by coincidence far more often when it is small.
     */
    private const int FLOOR = 6;

    public function sin(): Sin
    {
        return new NearDuplicateElement();
    }

    public function groupKey(Located $finding, BaseCodebase $codebase): ?string
    {
        return $finding instanceof ElementMatch ? $finding->shapeSignature() : null;
    }

    public function find(Codebase $components): array
    {
        $candidates = $components
            ->whereElement()
            ->ofAtLeastSize(self::FLOOR)
            ->get();

        $repeated = [];

        foreach ($this->recurringBuckets($candidates, $components) as $bucket) {
            $repeated[$bucket[0]->shapeSignature()] = true;
        }

        $copies = array_count_values(array_map(static fn (ElementMatch $match): string => $match->structureHash(), $candidates));

        return array_values(array_filter(
            $candidates,
            fn (ElementMatch $match): bool => isset($repeated[$match->shapeSignature()])
                && $copies[$match->structureHash()] === 1
                && ! $this->nestedInRepeat($match, $repeated),
        ));
    }

    /**
     * Is this block inside a larger block whose skeleton itself repeats? Then the outer one is the
     * finding and this is a piece of it.
     *
     * @param  array<string, true>  $repeated
     */
    private function nestedInRepeat(Element $element, array $repeated): bool
    {
        foreach ($element->ancestors() as $ancestor) {
            if ($ancestor->isElement() && isset($repeated[$ancestor->shapeSignature()])) {
                return true;
            }
        }

        return false;
    }
}
