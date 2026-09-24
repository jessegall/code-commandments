<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Testing;

use JesseGall\CodeCommandments\Detector;
use JesseGall\CodeCommandments\Language;
use JesseGall\CodeCommandments\Support\ClassName;
use JesseGall\CodeCommandments\Support\FileTree;

/**
 * The fixture files that ARE a rule's worked example. A file carrying `@example Name bad` or
 * `@example Name good`, in its own comment syntax, is published whole as that half of the rule's example.
 * A marker binds to one declaration, and what a rule is about does not always sit inside one — the import
 * that crosses a layer, the cycle two files tell between them — so this is how a fixture says which code
 * the example is.
 */
final class ExampleFiles
{
    private const string MARKER = '/^@example\s+(\w+)\s+(bad|good)$/';

    /**
     * What a PHP file with no class or function (a config file) hangs its `#[Sinful]` marker on.
     */
    private const string FILE_SCOPE_MARKER_HOST = 'static fn (): null => null;';

    /**
     * @param  array<string, list<MarkedSource>>  $bad  the files telling a Bad, by the Name each is marked with
     * @param  array<string, list<MarkedSource>>  $good  the files telling a Good, by the Name each is marked with
     */
    private function __construct(
        private readonly array $bad,
        private readonly array $good,
    ) {}

    /**
     * The example files under the fixture at $path.
     */
    public static function in(string $path): self
    {
        $halves = ['bad' => [], 'good' => []];

        // Sorted, so a half told by several files reads the same on every filesystem.
        $files = iterator_to_array(FileTree::sourcesIn($path), preserve_keys: false);
        sort($files);

        foreach ($files as $file) {
            $language = Language::ofFile($file);
            $lines = explode("\n", (string) file_get_contents($file));

            foreach (self::markers($lines, $language) as [$name, $half]) {
                $halves[$half][$name][] = new MarkedSource(
                    file: $file,
                    source: self::published($lines, $language),
                    heading: $language->comment('in ' . substr($file, strlen(rtrim($path, '/')) + 1)),
                );
            }
        }

        return new self($halves['bad'], $halves['good']);
    }

    /**
     * $examples with every rule that has example files told by them instead — per language, so a rule marked
     * in a template and a module keeps the half no file replaced.
     *
     * @param  array<class-string<Detector>, list<Example>>  $examples
     * @param  list<Detector>  $detectors
     * @return array<class-string<Detector>, list<Example>>
     */
    public function over(array $examples, array $detectors): array
    {
        foreach ($detectors as $detector) {
            $bad = self::byLanguage($this->bad($detector));
            $good = self::byLanguage($this->good($detector));

            foreach (array_keys($bad + $good) as $language) {
                $example = new Example(new Comparison(
                    isset($bad[$language]) ? ExampleText::group($bad[$language], lift: false) : null,
                    isset($good[$language]) ? ExampleText::group($good[$language], lift: false) : null,
                ), Language::from($language));

                $examples[$detector::class] = [
                    ...array_filter($examples[$detector::class] ?? [], static fn (Example $other): bool => $other->language->value !== $language),
                    $example,
                ];
            }
        }

        return $examples;
    }

    /**
     * The files marked as $detector's Bad.
     *
     * @return list<MarkedSource>
     */
    public function bad(Detector $detector): array
    {
        return self::markedFor($this->bad, $detector);
    }

    /**
     * The files marked as $detector's Good.
     *
     * @return list<MarkedSource>
     */
    public function good(Detector $detector): array
    {
        return self::markedFor($this->good, $detector);
    }

    /**
     * Every Name an `@example` marker carries, whatever half it tells.
     *
     * @return list<string>
     */
    public function names(): array
    {
        return array_keys($this->bad + $this->good);
    }

    /**
     * The Name and half every `@example` line in $lines carries.
     *
     * @param  list<string>  $lines
     * @return list<array{0: string, 1: string}>
     */
    private static function markers(array $lines, Language $language): array
    {
        $markers = [];

        foreach ($lines as $line) {
            if ($language->isCommentLine($line) && preg_match(self::MARKER, self::words($line), $match) === 1) {
                $markers[] = [$match[1], $match[2]];
            }
        }

        return $markers;
    }

    /**
     * @param  array<string, list<MarkedSource>>  $half
     * @return list<MarkedSource>
     */
    private static function markedFor(array $half, Detector $detector): array
    {
        return array_merge(...array_map(static fn (string $name): array => $half[$name] ?? [], self::namesOf($detector)));
    }

    /**
     * @param  list<MarkedSource>  $files
     * @return array<string, list<MarkedSource>>
     */
    private static function byLanguage(array $files): array
    {
        $byLanguage = [];

        foreach ($files as $file) {
            $byLanguage[Language::ofFile($file->file)->value][] = $file;
        }

        return $byLanguage;
    }

    /**
     * Every name a marker may call $detector by — its class or its sin's, short or whole, or the sin's id.
     *
     * @return list<string>
     */
    public static function namesOf(Detector $detector): array
    {
        return array_values(array_unique([
            $detector::class,
            ClassName::short($detector::class),
            $detector->sin()::class,
            ClassName::short($detector->sin()::class),
            $detector->sin()->name(),
        ]));
    }

    /**
     * The file as a reader of the example sees it: without the fixture's markers, the imports that bring
     * them in, the closure a config file's marker rides, or the PHP opening ceremony, and with no run of
     * blank lines left where they stood.
     *
     * @param  list<string>  $lines
     */
    private static function published(array $lines, Language $language): string
    {
        $kept = array_filter($lines, static fn (string $line): bool => ! self::isCeremony($line, $language));

        return trim((string) preg_replace("/\n{3,}/", "\n\n", implode("\n", $kept)));
    }

    private static function isCeremony(string $line, Language $language): bool
    {
        $text = trim($line);

        $phpCeremony = $language === Language::Php && (
            in_array($text, ['<?php', 'declare(strict_types=1);', self::FILE_SCOPE_MARKER_HOST], true)
            || str_starts_with($text, 'use JesseGall\\CodeCommandments\\')
            || str_starts_with($text, '#[Sinful(')
            || str_starts_with($text, '#[Fixed(')
            || str_starts_with($text, '#[Righteous(')
        );

        // A marker in any language's comment, not only the file's own: a Vue file's sit in its `<script>` too.
        return $phpCeremony || (
            array_any(Language::cases(), static fn (Language $any): bool => $any->isCommentLine($line))
            && DeclarationMarkers::isMarkerComment(self::words($line))
        );
    }

    /**
     * A comment line's words, without the delimiters that open and close it.
     */
    private static function words(string $line): string
    {
        return trim((string) preg_replace('/^\s*(?:\/\/|#|<!--|\/\*+|\*)\s*|\s*(?:-->|\*\/)\s*$/', '', $line));
    }
}
