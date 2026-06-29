<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Frontend;

use JesseGall\CodeCommandments\Vue\Codebase;
use JesseGall\CodeCommandments\Vue\Detector;
use JesseGall\CodeCommandments\Vue\Element;
use JesseGall\CodeCommandments\Vue\ElementMatch;

/**
 * Two-or-more identical blocks of template markup — the same tags, attributes and
 * children, copy-pasted (the comparison is by STRUCTURE, blind to formatting,
 * whitespace and line numbers). Repeated markup is one component waiting to be born:
 * extract it once and use it in each place. Points at vue-components.
 *
 * Only blocks of real substance count (a {@see FLOOR}-element floor skips a stray
 * repeated `<br>` or empty `<div>`), and only the LARGEST duplicated block is
 * flagged — its inner pieces are duplicated too, but extracting the whole is the fix.
 */
final class DuplicateElementDetector implements Detector
{
    private const int FLOOR = 3;

    public function skill(): string
    {
        return 'vue-components';
    }

    public function find(Codebase $components): array
    {
        $candidates = $components
            ->whereElement()
            ->where(static fn (Element $element): bool => $element->subtreeSize() >= self::FLOOR)
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
        for ($ancestor = $element->parent; $ancestor !== null; $ancestor = $ancestor->parent) {
            if ($ancestor->isElement() && isset($repeated[$ancestor->structureHash()])) {
                return true;
            }
        }

        return false;
    }
}
