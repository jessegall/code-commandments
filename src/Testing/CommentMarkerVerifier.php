<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Testing;

use JesseGall\CodeCommandments\Codebase as BaseCodebase;
use JesseGall\CodeCommandments\Vue\Codebase;
use JesseGall\CodeCommandments\Frontend\Detector;
use JesseGall\CodeCommandments\Vue\Element;
use JesseGall\CodeCommandments\Vue\Sfc;

/**
 * Frontend twin of SinfulMarkerVerifier; runs detectors over Vue fixtures and checks them
 * against `<!-- @sin DetectorName -->` comment markers.
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
        // Declaration-space `@sin` markers (on a `type`/`interface`) — the counterpart of
        // the template markers below, for a detector that flags a type, not an element.
        $declarationMarks = DeclarationMarkers::in($codebase, 'sin');

        $results = [];

        foreach ($detectors as $detector) {
            $name = (new \ReflectionClass($detector))->getShortName();
            // A `@sin` marker names this detector's SIN (`@sin SwitchCase`) or, still, the
            // detector (`@sin SwitchCaseDetector`). Accept either short name.
            $names = [$name, (new \ReflectionClass($detector->sin()))->getShortName()];

            $marked = [];
            foreach ($codebase->components() as $component) {
                $this->collectMarked($component->template, $component, $names, $marked);
            }

            foreach ($names as $named) {
                $marked = [...$marked, ...($declarationMarks[$named] ?? [])];
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
     * Record the location of every element preceded (as a sibling) by a `@sin` comment
     * naming any of $names (the detector's sin or the detector).
     *
     * @param  list<string>  $names
     * @param  list<string>  $marked
     */
    private function collectMarked(Element $node, Sfc $component, array $names, array &$marked): void
    {
        $pending = [];

        foreach ($node->children as $child) {
            if ($child->isComment()) {
                // A run of `@sin` comments all mark the element that follows — markers
                // are repeatable, so one element can be several detectors' sins.
                if (preg_match('/@sin\s+(\w+)/', $child->text, $match) === 1) {
                    $pending[] = $match[1];
                }

                continue;
            }

            if ($child->isElement()) {
                if (array_intersect($names, $pending) !== []) {
                    $marked[] = $component->path . ':' . $child->line;
                }

                $pending = [];
            }

            $this->collectMarked($child, $component, $names, $marked);
        }
    }
}
