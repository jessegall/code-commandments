<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Testing;

use JesseGall\CodeCommandments\Language;

use JesseGall\CodeCommandments\Vue\Codebase;
use JesseGall\CodeCommandments\Frontend\Detector;
use JesseGall\CodeCommandments\Vue\Element;
use JesseGall\CodeCommandments\Vue\Sfc;

/**
 * The frontend twin of {@see FixtureExamples}: pulls each Vue detector's worked example
 * from the `.vue` fixture — the `<!-- @sin Name -->`-marked element (BAD) and its
 * `<!-- @righteous Name -->` twin (GOOD) — as real, parsed template source. Same shape
 * as the backend extractor (`array<detector-class, list<Example>>`), so the
 * {@see \JesseGall\CodeCommandments\Skills\SkillRenderer} treats both engines identically.
 */
final class VueFixtureExamples
{
    /**
     * @param  list<Detector>  $detectors
     * @return array<class-string<Detector>, list<Example>>
     */
    public static function extract(Codebase $codebase, array $detectors): array
    {
        return MarkedExamples::extract($detectors, static fn (string $marker) => self::sourcesByMarker($codebase, $marker), Language::Vue);
    }

    /**
     * Every element marked by a `@{$marker} Name` comment, grouped by the Name.
     *
     * @return array<string, list<array{file: string, source: string}>>
     */
    private static function sourcesByMarker(Codebase $codebase, string $marker): array
    {
        $sources = [];

        foreach ($codebase->components() as $component) {
            self::collect($component->template, $component, $marker, $sources);
        }

        return array_merge_recursive($sources, MarkedExamples::moduleSources($codebase->modules(), $marker));
    }

    /**
     * @param  array<string, list<array{file: string, source: string}>>  $sources
     */
    private static function collect(Element $node, Sfc $component, string $marker, array &$sources): void
    {
        $pending = [];

        foreach ($node->children as $child) {
            if ($child->isComment()) {
                if (preg_match('/@' . $marker . '\s+(\w+)/', $child->text, $m) === 1) {
                    $pending[] = $m[1];
                }

                continue;
            }

            if ($child->isElement()) {
                foreach ($pending as $name) {
                    $sources[$name][] = [
                        'file' => $component->path,
                        'heading' => Language::Vue->comment('in ' . MarkedExamples::name($component->path)),
                        'source' => self::source($child, $component),
                    ];
                }

                $pending = [];
            }

            self::collect($child, $component, $marker, $sources);
        }
    }

    /**
     * A marked element's template source, dedented to read as a top-level snippet. The
     * slice starts at the element's `<` (mid-line), so the element's own indentation is
     * prepended first — then every line shares it and {@see dedent} strips it uniformly.
     */
    private static function source(Element $element, Sfc $component): string
    {
        $lineStart = strrpos(substr($component->source, 0, $element->start), "\n");
        $indent = substr($component->source, $lineStart === false ? 0 : $lineStart + 1, $element->start - ($lineStart === false ? 0 : $lineStart + 1));
        $raw = $indent . substr($component->source, $element->start, $element->end - $element->start);

        return ExampleText::dedent(explode("\n", $raw));
    }
}
