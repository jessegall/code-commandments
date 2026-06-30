<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Architecture;

use PHPUnit\Framework\TestCase;

/**
 * The cardinal rule, enforced — and HARD: the layers that UNDERSTAND or REWRITE code do it
 * through the AST, never by scraping the source. This guards the whole detection + scribe
 * stack and the entire Vue engine against the two ways that rule gets cheated:
 *
 *   1. REGEX (`preg_*`) anywhere but the delimiter tokenizers — a regex over code is a
 *      parser the engine should already provide; if a predicate/write is missing, add the
 *      TOOL (an AST method, a query selector, a write op), don't `preg_replace` the source.
 *   2. Hand-rolled SOURCE SCANNING (`strpos`/`strrpos`/`str_contains`) in the FRONTEND
 *      understanding/write layer — the "fake regex" dodge (hunt for `<Tag`, scan the script
 *      for a name). The element/expression AST already knows positions; query it.
 *
 * The ONLY exemptions are the genuine LEXERS (where char/delimiter scanning IS the job) and
 * the byte-offset utility {@see \JesseGall\CodeCommandments\Scribes\Span} (newline math).
 * Prefix/suffix classification (`str_starts_with`/`str_ends_with` on a name/FQCN/path) is
 * allowed — it reads a known token, it doesn't parse the source.
 */
final class NoRegexInParsingLayerTest extends TestCase
{
    private const array REGEX = ['preg_match', 'preg_match_all', 'preg_replace', 'preg_replace_callback', 'preg_split', 'preg_quote'];

    private const array SCAN = ['strpos', 'strrpos', 'strstr'];

    /** Lexers (and the offset util) — the only place raw scanning is the job. */
    private const array LEXERS = ['Tokenizer.php', 'Sfc.php', 'Attributes.php', 'Script.php', 'Interpolation.php', 'Lexer.php', 'Parser.php', 'Span.php'];

    public function test_no_regex_in_the_detection_scribe_or_engine_layer(): void
    {
        $this->assertNoneOf(self::REGEX, $this->phpIn('Detectors', 'Scribes', 'Vue'), 'regex over code — compose the AST, don\'t scrape it');
    }

    public function test_no_handrolled_source_scanning_in_the_frontend_engine(): void
    {
        $this->assertNoneOf(self::SCAN, $this->phpIn('Detectors/Frontend', 'Scribes/Frontend', 'Vue'), 'hand-rolled source scanning — the AST knows positions, query it');
    }

    /**
     * @param  list<string>  $functions
     * @param  list<string>  $files
     */
    private function assertNoneOf(array $functions, array $files, string $why): void
    {
        $offenders = [];

        foreach ($files as $file) {
            if (in_array(basename($file), self::LEXERS, true)) {
                continue;
            }

            $source = (string) file_get_contents($file);

            foreach ($functions as $function) {
                if (str_contains($source, $function . '(')) {
                    $offenders[] = substr($file, (int) (strpos($file, 'src/') ?: 0)) . " uses {$function}";
                }
            }
        }

        sort($offenders);

        $this->assertSame([], $offenders, "{$why}:\n" . implode("\n", $offenders));
    }

    /**
     * @return list<string>
     */
    private function phpIn(string ...$dirs): array
    {
        $files = [];

        foreach ($dirs as $dir) {
            $base = __DIR__ . '/../../src/' . $dir;

            if (! is_dir($base)) {
                continue;
            }

            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($base, \FilesystemIterator::SKIP_DOTS));

            foreach ($iterator as $file) {
                if ($file->isFile() && $file->getExtension() === 'php') {
                    $files[] = $file->getPathname();
                }
            }
        }

        return $files;
    }
}
