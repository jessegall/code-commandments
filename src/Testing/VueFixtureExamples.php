<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Testing;

use JesseGall\CodeCommandments\Vue\Codebase;
use JesseGall\CodeCommandments\Detectors\RecurrenceDetector;
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
        $sinful = self::sourcesByMarker($codebase, 'sin');
        $righteous = self::sourcesByMarker($codebase, 'righteous');

        $examples = [];

        foreach ($detectors as $detector) {
            $keys = [(new \ReflectionClass($detector->sin()))->getShortName(), (new \ReflectionClass($detector))->getShortName()];
            $examples[$detector::class] = self::perLanguage($detector, ExampleText::forKeys($sinful, $keys), ExampleText::forKeys($righteous, $keys));
        }

        return $examples;
    }

    /**
     * One example PER LANGUAGE the rule is marked in. A frontend discipline can hold in a template
     * and in a module — `MirroredServerType` is marked in both — and showing only whichever the
     * scan reached first teaches half the rule: a reader working in `.ts` needs the `.ts` example,
     * not a `.vue` one they have to translate.
     *
     * @param  list<array{file: string, source: string}>  $bad
     * @param  list<array{file: string, source: string}>  $good
     * @return list<Example>
     */
    private static function perLanguage(Detector $detector, array $bad, array $good): array
    {
        $badByLanguage = self::byLanguage($bad);
        $goodByLanguage = self::byLanguage($good);

        if ($badByLanguage === []) {
            return [ExampleText::pair($bad, $good, 'file')->in(Language::Vue)];
        }

        $examples = [];

        foreach ($badByLanguage as $language => $marked) {
            $example = ExampleText::pair($marked, $goodByLanguage[$language] ?? [], 'file')->in(Language::from($language));

            // A repeated block shown once is not repeated — the same rule as the backend's
            // duplicates, asked of the same interface, and labelled with the component it is in.
            $examples[] = $detector instanceof RecurrenceDetector && count($marked) > 1
                ? $example->withBad(ExampleText::group($marked, 'file', lift: false, open: '<!--', close: ' -->'))
                : $example;
        }

        return $examples;
    }

    /**
     * Marked sources grouped by the language of the file each was marked in.
     *
     * @param  list<array{file: string, source: string}>  $sources
     * @return array<string, list<array{file: string, source: string}>>
     */
    private static function byLanguage(array $sources): array
    {
        $grouped = [];

        foreach ($sources as $source) {
            $grouped[Language::ofFile($source['file'])->value][] = $source;
        }

        return $grouped;
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

        return array_merge_recursive($sources, self::inModules($codebase, $marker));
    }

    /**
     * The marked DECLARATIONS of every `.ts` module and `<script>` block — a `// @sin Name` above a
     * `class`, `interface`, `type` or `function`.
     *
     * A frontend rule is not always about markup, and a marker in a module was invisible here while
     * this only walked templates: the rule's own example came back empty, so a skill about
     * TypeScript printed no TypeScript. The module's parsed nodes carry their source span, so the
     * example is the declaration exactly as written.
     *
     * @return array<string, list<array{file: string, source: string}>>
     */
    private static function inModules(Codebase $codebase, string $marker): array
    {
        $sources = [];

        foreach ($codebase->modules() as $module) {
            $lines = explode("\n", $module->source);

            foreach ($module->module->children() as $node) {
                foreach (DeclarationMarkers::markersAbove($lines, $module->lineAt($node->start), $marker) as $name) {
                    $sources[$name][] = [
                        'file' => $module->file,
                        'source' => ExampleText::dedent(explode("\n", $module->spanAt($node->start, $node->end)->text())),
                    ];
                }
            }
        }

        return $sources;
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
                    $sources[$name][] = ['file' => $component->path, 'source' => self::source($child, $component)];
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
