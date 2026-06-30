<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Vue;

use JesseGall\CodeCommandments\Detectors\Frontend\DeepDataReachDetector;
use JesseGall\CodeCommandments\Detectors\Frontend\DuplicateElementDetector;
use JesseGall\CodeCommandments\Detectors\Frontend\SwitchCaseDetector;
use JesseGall\CodeCommandments\Testing\DetectorResult;
use JesseGall\CodeCommandments\Tests\Support\FixtureTestCase;
use JesseGall\CodeCommandments\Vue\Codebase;
use JesseGall\CodeCommandments\Vue\Detector;
use JesseGall\CodeCommandments\Vue\Element;
use JesseGall\CodeCommandments\Vue\Sfc;

/**
 * The frontend self-checking fixture — same flow as the backend's (see {@see
 * FixtureTestCase}), only over `.vue`. A `<!-- @sin DetectorName -->` comment marks
 * the element that follows as a sin (the Vue analog of `#[Sinful]`); each detector
 * must flag exactly its marked elements and fire on ≥3 diverse scenarios.
 */
final class FrontendFixtureTest extends FixtureTestCase
{
    /**
     * @return list<Detector>
     */
    private function detectors(): array
    {
        return [new SwitchCaseDetector(), new DuplicateElementDetector(), new DeepDataReachDetector()];
    }

    protected function markerResults(): array
    {
        $codebase = $this->fixture();
        $results = [];

        foreach ($this->detectors() as $detector) {
            $name = (new \ReflectionClass($detector))->getShortName();

            $expected = [];
            foreach ($codebase->components() as $component) {
                $this->collectMarked($component->template, $component, $name, $expected);
            }

            $flagged = array_map(static fn ($match): string => $match->location(), $detector->find($codebase));

            $results[] = new DetectorResult(
                $name,
                array_values(array_diff($expected, $flagged)),   // marked but not flagged
                array_values(array_diff($flagged, $expected)),    // flagged but not marked
            );
        }

        return $results;
    }

    protected function scenarios(): array
    {
        $codebase = $this->fixture();
        $scenarios = [];

        foreach ($this->detectors() as $detector) {
            $name = (new \ReflectionClass($detector))->getShortName();
            // The scenario is the enclosing COMPONENT (the frontend's "class"), so two
            // findings in the same .vue collapse to one — mirroring the backend.
            $scenarios[$name] = array_map(
                static fn ($match): array => ['file' => $match->file(), 'source' => $match->sfc->source],
                $detector->find($codebase),
            );
        }

        return $scenarios;
    }

    /**
     * Record the location of every element preceded (as a sibling) by a
     * `@sin {$detector}` comment.
     *
     * @param  list<string>  $expected
     */
    private function collectMarked(Element $node, Sfc $component, string $detector, array &$expected): void
    {
        $pending = null;

        foreach ($node->children as $child) {
            if ($child->isComment()) {
                $pending = preg_match('/@sin\s+(\w+)/', $child->text, $match) === 1 ? $match[1] : null;

                continue;
            }

            if ($child->isElement()) {
                if ($pending === $detector) {
                    $expected[] = $component->path . ':' . $child->line;
                }

                $pending = null;
            }

            $this->collectMarked($child, $component, $detector, $expected);
        }
    }

    private function fixture(): Codebase
    {
        return Codebase::scan(__DIR__ . '/../Fixtures/shop-frontend');
    }
}
