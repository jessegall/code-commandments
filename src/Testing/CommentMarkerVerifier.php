<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Testing;

use JesseGall\CodeCommandments\Codebase as BaseCodebase;
use JesseGall\CodeCommandments\Vue\Codebase;
use JesseGall\CodeCommandments\Vue\Detector;
use JesseGall\CodeCommandments\Vue\Element;
use JesseGall\CodeCommandments\Vue\Sfc;

/**
 * The frontend twin of {@see SinfulMarkerVerifier}: run each detector over the `.vue`
 * fixture and check it against the `<!-- @sin DetectorName -->` comments — the Vue
 * analog of `#[Sinful]`. A comment marks the element that immediately follows it (a
 * sibling); a detector passes when it flags every marked element and nothing else.
 *
 * Same contract as the backend verifier — a list of {@see DetectorResult} — so the
 * shared {@see \JesseGall\CodeCommandments\Tests\Support\FixtureTestCase} treats both
 * engines identically.
 */
final class CommentMarkerVerifier implements MarkerVerifier
{
    /**
     * @param  Codebase  $codebase
     * @param  list<Detector>  $detectors
     * @return list<DetectorResult>
     */
    public function verify(BaseCodebase $codebase, array $detectors): array
    {
        $results = [];

        foreach ($detectors as $detector) {
            $name = (new \ReflectionClass($detector))->getShortName();

            $marked = [];
            foreach ($codebase->components() as $component) {
                $this->collectMarked($component->template, $component, $name, $marked);
            }

            $flagged = array_map(static fn ($match): string => $match->location(), $detector->find($codebase));

            $results[] = new DetectorResult(
                $name,
                array_values(array_diff($marked, $flagged)),   // marked but not flagged
                array_values(array_diff($flagged, $marked)),    // flagged but not marked
            );
        }

        return $results;
    }

    /**
     * Record the location of every element preceded (as a sibling) by a
     * `@sin {$detector}` comment.
     *
     * @param  list<string>  $marked
     */
    private function collectMarked(Element $node, Sfc $component, string $detector, array &$marked): void
    {
        $pending = null;

        foreach ($node->children as $child) {
            if ($child->isComment()) {
                $pending = preg_match('/@sin\s+(\w+)/', $child->text, $match) === 1 ? $match[1] : null;

                continue;
            }

            if ($child->isElement()) {
                if ($pending === $detector) {
                    $marked[] = $component->path . ':' . $child->line;
                }

                $pending = null;
            }

            $this->collectMarked($child, $component, $detector, $marked);
        }
    }
}
