<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Vue;

use JesseGall\CodeCommandments\Detectors\Frontend\DuplicateElementDetector;
use JesseGall\CodeCommandments\Detectors\Frontend\SwitchCaseDetector;
use JesseGall\CodeCommandments\Vue\Codebase;
use JesseGall\CodeCommandments\Vue\Detector;
use JesseGall\CodeCommandments\Vue\Element;
use JesseGall\CodeCommandments\Vue\Sfc;
use PHPUnit\Framework\TestCase;

/**
 * The frontend self-checking fixture — the twin of FixtureDetectorTest for `.vue`.
 * `tests/Fixtures/shop-frontend` is a small Vue app that is never run, only parsed.
 * A `<!-- @sin DetectorName -->` comment marks the element that immediately follows
 * as a sin (the comment IS the spec, like `#[Sinful]` on a PHP node). Every detector
 * must flag exactly its marked elements — no misses, no surprises (the whole unmarked
 * fixture is the false-positive guard).
 */
final class FrontendFixtureTest extends TestCase
{
    /**
     * @return list<Detector>
     */
    private function detectors(): array
    {
        return [new SwitchCaseDetector(), new DuplicateElementDetector()];
    }

    public function test_each_detector_flags_exactly_its_marked_elements(): void
    {
        $codebase = Codebase::scan(__DIR__ . '/../Fixtures/shop-frontend');

        foreach ($this->detectors() as $detector) {
            $name = (new \ReflectionClass($detector))->getShortName();

            $expected = [];
            foreach ($codebase->components() as $component) {
                $this->collectMarked($component->template, $component, $name, $expected);
            }

            $actual = array_map(static fn ($match): string => $match->location(), $detector->find($codebase));

            sort($expected);
            sort($actual);

            $this->assertSame($expected, $actual, "{$name}: marked sins and flagged sins differ");
        }
    }

    public function test_the_fixture_actually_marks_something(): void
    {
        $codebase = Codebase::scan(__DIR__ . '/../Fixtures/shop-frontend');

        $this->assertGreaterThanOrEqual(3, count(new SwitchCaseDetector()->find($codebase)), 'expect ≥3 diverse marked scenarios');
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
}
