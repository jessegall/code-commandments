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
     * @param  Closure(string): array<string, list<MarkedSource>>  $sources  marker => sources by Name
     * @param  Language  $fallback  the language an example is in when no marked file says
     * @param  array<class-string<Detector>, array<string, array<int, string>>>  $groups  each recurring rule's findings by file and line, with the group each recurs in
     * @return array<class-string<Detector>, list<Example>>
     */
    public static function extract(array $detectors, Closure $sources, Language $fallback, array $groups = []): array
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
                $groups[$detector::class] ?? [],
            );
        }

        return $examples;
    }

    /**
     * The declarations and statements of $modules marked `@{$marker} Name`, grouped by the Name — a marker
     * names the outermost node its line opens, shown dedented and headed with the file it is in, in the
     * module's own comment syntax. A statement is shown as the function it sits in and a field as the type
     * it sits in, since the sin is rarely legible without the rest of it, and each is shown with the
     * comments directly above it, which a sin about comments is made of. A function marked twice is shown
     * once.
     *
     * @param  iterable<ParsedModule>  $modules
     * @return array<string, list<MarkedSource>>
     */
    public static function moduleSources(iterable $modules, string $marker): array
    {
        $sources = [];

        foreach ($modules as $module) {
            $lines = explode("\n", $module->source);
            $shown = [];

            $functions = $module->functionSpans();
            $types = $module->typeSpans();

            foreach ($module->nodeSpans() as [$start, $end]) {
                $line = $module->lineAt($start);

                // A method's parameters and body begin on the line that opens it; the walk reaches the
                // outermost node first, and that is the one a marker names.
                if (isset($shown[$line])) {
                    continue;
                }

                $shown[$line] = true;
                $names = DeclarationMarkers::markersAbove($lines, $line, $marker);

                if ($names === []) {
                    continue;
                }

                [$from, $to] = self::innermost($functions, $start, $end) ?? self::innermost($types, $start, $end) ?? [$start, $end];
                $opens = $module->lineAt($from);
                $indent = substr($lines[$opens - 1], 0, strspn($lines[$opens - 1], " \t"));
                $text = [...self::commentsAbove($lines, $opens, $module->language()), ...explode("\n", $indent . $module->spanAt($from, $to)->text())];

                foreach ($names as $name) {
                    $sources[$name]["{$module->file}:{$from}"] = new MarkedSource(
                        file: $module->file,
                        source: ExampleText::dedent(array_values(array_filter(
                            $text,
                            static fn (string $line): bool => ! DeclarationMarkers::isMarkerLine($line, $module->language()),
                        ))),
                        heading: $module->language()->comment('in ' . self::name($module->file)),
                        firstLine: $module->lineAt($from),
                        lastLine: $module->lineAt(max($from, $to - 1)),
                    );
                }
            }
        }

        return array_map(array_values(...), $sources);
    }

    /**
     * The innermost of $spans holding `[$start, $end)`, or null when none does.
     *
     * @param  list<array{0: int, 1: int}>  $spans
     * @return ?array{0: int, 1: int}
     */
    private static function innermost(array $spans, int $start, int $end): ?array
    {
        $innermost = null;

        foreach ($spans as [$from, $to]) {
            if ($from <= $start && $end <= $to && ($innermost === null || $from >= $innermost[0])) {
                $innermost = [$from, $to];
            }
        }

        return $innermost;
    }

    /**
     * The run of comment lines directly above line $at (1-based), in order — what a reader sees on top of a
     * declaration, and what a sin about comments consists of.
     *
     * @param  list<string>  $lines
     * @return list<string>
     */
    private static function commentsAbove(array $lines, int $at, Language $language): array
    {
        $comments = [];

        for ($n = $at - 1; $n >= 1 && trim($lines[$n - 1]) !== '' && $language->isCommentLine($lines[$n - 1]); $n--) {
            array_unshift($comments, $lines[$n - 1]);
        }

        return $comments;
    }

    /**
     * One example PER LANGUAGE the rule is marked in. A frontend discipline can hold in a template
     * and in a module — `MirroredServerType` is marked in both — and showing only whichever the
     * scan reached first teaches half the rule: a reader working in `.ts` needs the `.ts` example,
     * not a `.vue` one they have to translate.
     *
     * @param  list<MarkedSource>  $bad
     * @param  list<MarkedSource>  $fixed
     * @param  list<MarkedSource>  $righteous
     * @param  array<string, array<int, string>>  $groups  the rule's findings by file and line, with the group each recurs in
     * @return list<Example>
     */
    private static function perLanguage(Detector $detector, array $bad, array $fixed, array $righteous, Language $fallback, array $groups): array
    {
        $badByLanguage = self::byLanguage($bad);
        $fixedByLanguage = self::byLanguage($fixed);
        $righteousByLanguage = self::byLanguage($righteous);

        if ($badByLanguage === []) {
            return [self::example($detector, $bad, $fixed, $righteous, $fallback, $bad, $groups)];
        }

        $examples = [];

        foreach ($badByLanguage as $language => $marked) {
            $examples[] = self::example(
                $detector,
                $marked,
                $fixedByLanguage[$language] ?? [],
                $righteousByLanguage[$language] ?? [],
                Language::from($language),
                $bad,
                $groups,
            );
        }

        return $examples;
    }

    /**
     * One language's example — the same assembly the backend does, over marked elements and module
     * nodes instead of PHP declarations.
     *
     * @param  list<MarkedSource>  $bad
     * @param  list<MarkedSource>  $fixed
     * @param  list<MarkedSource>  $righteous
     * @param  list<MarkedSource>  $everyBad  the rule's sinful sources in every language — a recurring group may cross from a module into a template
     * @param  array<string, array<int, string>>  $groups  the rule's findings by file and line, with the group each recurs in
     */
    private static function example(Detector $detector, array $bad, array $fixed, array $righteous, Language $language, array $everyBad, array $groups): Example
    {
        // Only a RESOLUTION spans blocks. A righteous look-alike falls back one at a time: two of
        // them in a component are two exemptions, not one repair told in two places.
        $resolution = ExampleText::resolution($bad, $fixed);
        $good = $resolution ?: $righteous;

        $example = ExampleText::pair($bad, $good)->in($language);

        // A component extracted out of a template is the fix; the one-line call that replaced the
        // markup is only where it went. Showing that line alone names a component nothing declares.
        if (count($resolution) > 1) {
            $example = $example->withGood(ExampleText::group($resolution, lift: false));
        }

        // A repeated block shown once is not repeated — the same rule as the backend's
        // duplicates, asked of the same interface, and labelled with the component it is in. Only the
        // group the example is anchored on: every other marked group is a scenario its Good leaves alone.
        $recurring = $detector instanceof RecurrenceDetector
            ? ExampleText::recurring($everyBad, ExampleText::anchor($bad, $good) ?? $bad[0], $groups)
            : [];

        return count($recurring) > 1
            ? $example->withBad(ExampleText::group($recurring, lift: false))
            : $example;
    }

    /**
     * Marked sources grouped by the language of the file each was marked in.
     *
     * @param  list<MarkedSource>  $sources
     * @return array<string, list<MarkedSource>>
     */
    private static function byLanguage(array $sources): array
    {
        $grouped = [];

        foreach ($sources as $source) {
            $grouped[Language::ofFile($source->file)->value][] = $source;
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
