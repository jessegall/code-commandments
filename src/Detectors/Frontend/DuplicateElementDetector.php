<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Frontend;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Sins\Frontend\DuplicateElement;
use JesseGall\CodeCommandments\Codebase as BaseCodebase;
use JesseGall\CodeCommandments\Detectors\RecurrenceDetector;
use JesseGall\CodeCommandments\Detectors\Repentable;
use JesseGall\CodeCommandments\Located;
use JesseGall\CodeCommandments\Scribes\Frontend\ExtractComponentScribe;
use JesseGall\CodeCommandments\Vue\Codebase;
use JesseGall\CodeCommandments\Frontend\Detector;
use JesseGall\CodeCommandments\Vue\Element;
use JesseGall\CodeCommandments\Vue\ElementMatch;

/**
 * Detects identical template blocks (structural comparison, blind to formatting); only substantial
 * blocks and the largest duplicate are flagged. A {@see RecurrenceDetector} — one block proves
 * nothing, the sin is the same shape appearing twice — which earns it the cross-file fixture proof
 * its backend siblings carry: one marked group must span TWO components.
 */
final class DuplicateElementDetector implements Detector, RecurrenceDetector, Repentable
{
    private const int FLOOR = 3;

    public function sin(): Sin
    {
        return new DuplicateElement();
    }

    public function groupKey(Located $finding, BaseCodebase $codebase): ?string
    {
        return $finding instanceof ElementMatch ? $finding->structureHash() : null;
    }

    public function scribe(): ExtractComponentScribe
    {
        return ExtractComponentScribe::forDuplicates();
    }

    public function find(Codebase $components): array
    {
        $candidates = $components
            ->whereElement()
            ->ofAtLeastSize(self::FLOOR)
            ->get();

        $repeated = $this->repeatedHashes($candidates);

        return array_values(array_filter(
            $candidates,
            fn (ElementMatch $match): bool =>
                isset($repeated[$match->structureHash()]) && ! $this->nestedInRepeat($match, $repeated),
        ));
    }

    /**
     * The structure hashes that occur two-or-more times.
     *
     * @param  list<ElementMatch>  $candidates
     * @return array<string, true>
     */
    private function repeatedHashes(array $candidates): array
    {
        $counts = [];

        foreach ($candidates as $candidate) {
            $counts[$candidate->structureHash()] = ($counts[$candidate->structureHash()] ?? 0) + 1;
        }

        return array_filter($counts, static fn (int $count): bool => $count >= 2);
    }

    /**
     * Is this block contained in a larger block that is itself duplicated? (Then the
     * outer one is the real finding; this is just a piece of it.)
     *
     * @param  array<string, true>  $repeated
     */
    private function nestedInRepeat(Element $element, array $repeated): bool
    {
        foreach ($element->ancestors() as $ancestor) {
            if ($ancestor->isElement() && isset($repeated[$ancestor->structureHash()])) {
                return true;
            }
        }

        return false;
    }
}
