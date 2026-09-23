<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Testing;

use Closure;
use JesseGall\CodeCommandments\Detector;
use JesseGall\CodeCommandments\Detectors\RecurrenceDetector;
use JesseGall\CodeCommandments\Language;
use JesseGall\CodeCommandments\ParsedModule;

/**
 * A comment-marked fixture's worked examples, assembled the same way whatever language marked them: each
 * detector's `@sin` sources (BAD) against its `@fixed` repair, or its `@righteous` look-alike where there
 * is none (GOOD), one example per language the rule is marked in. An engine supplies only where its marks
 * are — {@see moduleSources} reads them off any {@see ParsedModule}.
 */
final class MarkedExamples
{
    /**
     * @param  list<Detector>  $detectors
     * @param  Closure(string): array<string, list<array{file: string, source: string}>>  $sources  marker => sources by Name
     * @param  Language  $fallback  the language an example is in when no marked file says
     * @return array<class-string<Detector>, list<Example>>
     */
    public static function extract(array $detectors, Closure $sources, Language $fallback): array
    {
        $sinful = $sources('sin');
        $fixed = $sources('fixed');
        $righteous = $sources('righteous');
        $examples = [];

        foreach ($detectors as $detector) {
            $keys = [new \ReflectionClass($detector->sin())->getShortName(), new \ReflectionClass($detector)->getShortName()];

            $examples[$detector::class] = self::perLanguage(
                $detector,
                ExampleText::forKeys($sinful, $keys),
                ExampleText::forKeys($fixed, $keys),
                ExampleText::forKeys($righteous, $keys),
                $fallback,
            );
        }

        return $examples;
    }

    /**
     * The declarations and statements of $modules marked `@{$marker} Name`, grouped by the Name — a marker
     * names the outermost node its line opens, shown dedented and headed with the file it is in, in the
     * module's own comment syntax. A statement is shown as the function it sits in, since the sin is
     * rarely legible without the rest of it, and a function marked twice is shown once.
     *
     * @param  iterable<ParsedModule>  $modules
     * @return array<string, list<array{file: string, heading: string, source: string}>>
     */
    public static function moduleSources(iterable $modules, string $marker): array
    {
        $sources = [];

        foreach ($modules as $module) {
            $lines = explode("\n", $module->source);
            $shown = [];

            $functions = $module->functionSpans();

            foreach ($module->nodeSpans() as [$start, $end]) {
                $line = $module->lineAt($start);

                // A method's parameters and body begin on the line that opens it; the walk reaches the
                // outermost node first, and that is the one a marker names.
                if (isset($shown[$line])) {
                    continue;
                }

                $shown[$line] = true;
                [$from, $to] = self::enclosingFunction($functions, $start, $end);
                $opens = $module->lineAt($from);
                $indent = substr($lines[$opens - 1], 0, strspn($lines[$opens - 1], " \t"));

                foreach (DeclarationMarkers::markersAbove($lines, $line, $marker) as $name) {
                    $sources[$name]["{$module->file}:{$from}"] = [
                        'file' => $module->file,
                        'heading' => $module->language()->comment('in ' . self::name($module->file)),
                        'source' => ExampleText::dedent(array_values(array_filter(
                            explode("\n", $indent . $module->spanAt($from, $to)->text()),
                            static fn (string $line): bool => ! DeclarationMarkers::isMarkerLine($line, $module->language()),
                        ))),
                    ];
                }
            }
        }

        return array_map(array_values(...), $sources);
    }

    /**
     * The innermost of $functions holding `[$start, $end)` — the span itself when no function does.
     *
     * @param  list<array{0: int, 1: int}>  $functions
     * @return array{0: int, 1: int}
     */
    private static function enclosingFunction(array $functions, int $start, int $end): array
    {
        $innermost = [$start, $end];

        foreach ($functions as [$from, $to]) {
            $holds = $from <= $start && $end <= $to;
            $inside = $innermost === [$start, $end] || $from >= $innermost[0];

            if ($holds && $inside) {
                $innermost = [$from, $to];
            }
        }

        return $innermost;
    }

    /**
     * One example PER LANGUAGE the rule is marked in. A frontend discipline can hold in a template
     * and in a module — `MirroredServerType` is marked in both — and showing only whichever the
     * scan reached first teaches half the rule: a reader working in `.ts` needs the `.ts` example,
     * not a `.vue` one they have to translate.
     *
     * @param  list<array{file: string, source: string}>  $bad
     * @param  list<array{file: string, source: string}>  $fixed
     * @param  list<array{file: string, source: string}>  $righteous
     * @return list<Example>
     */
    private static function perLanguage(Detector $detector, array $bad, array $fixed, array $righteous, Language $fallback): array
    {
        $badByLanguage = self::byLanguage($bad);
        $fixedByLanguage = self::byLanguage($fixed);
        $righteousByLanguage = self::byLanguage($righteous);

        if ($badByLanguage === []) {
            return [self::example($detector, $bad, $fixed, $righteous, $fallback)];
        }

        $examples = [];

        foreach ($badByLanguage as $language => $marked) {
            $examples[] = self::example(
                $detector,
                $marked,
                $fixedByLanguage[$language] ?? [],
                $righteousByLanguage[$language] ?? [],
                Language::from($language),
            );
        }

        return $examples;
    }

    /**
     * One language's example — the same assembly the backend does, over marked elements and module
     * nodes instead of PHP declarations.
     *
     * @param  list<array{file: string, source: string}>  $bad
     * @param  list<array{file: string, source: string}>  $fixed
     * @param  list<array{file: string, source: string}>  $righteous
     */
    private static function example(Detector $detector, array $bad, array $fixed, array $righteous, Language $language): Example
    {
        // Only a RESOLUTION spans blocks. A righteous look-alike falls back one at a time: two of
        // them in a component are two exemptions, not one repair told in two places.
        $resolution = ExampleText::resolution($bad, $fixed, 'file');
        $good = $resolution ?: $righteous;

        $example = ExampleText::pair($bad, $good, 'file')->in($language);

        // A component extracted out of a template is the fix; the one-line call that replaced the
        // markup is only where it went. Showing that line alone names a component nothing declares.
        if (count($resolution) > 1) {
            $example = $example->withGood(ExampleText::group($resolution, lift: false));
        }

        // A repeated block shown once is not repeated — the same rule as the backend's
        // duplicates, asked of the same interface, and labelled with the component it is in.
        return $detector instanceof RecurrenceDetector && count($bad) > 1
            ? $example->withBad(ExampleText::group($bad, lift: false))
            : $example;
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
     * How a file is NAMED in a published example — its basename.
     *
     * The path a scan resolved is THIS machine's: a heading built from it printed the author's home
     * directory into every shipped skill, where the component's name is the whole of what a reader
     * needs to tell one block from the next.
     */
    public static function name(string $file): string
    {
        return basename($file);
    }

}
