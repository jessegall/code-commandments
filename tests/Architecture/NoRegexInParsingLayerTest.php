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
 *   2. Hand-rolled SOURCE SCANNING (`strpos`/`strrpos`/`strstr`) across the WHOLE detection +
 *      scribe + engine layer — the "fake regex" dodge (hunt for `<Tag`, scan a docblock for a
 *      type). The AST already knows positions; query it, or add a reusable primitive to the
 *      offset util {@see \JesseGall\CodeCommandments\Scribes\Span} so every scribe shares it.
 *   3. Hand-rolled CHAR LEXING (`ctype_*`) in the BACKEND detector/scribe layer — the backend
 *      has php-parser + {@see Span}; building a token/skipping whitespace by hand is a lexer the
 *      engine already provides, so `ctype_*` there is always a bypass.
 *   4. Bespoke REWRITING (`->edit(`) scattered across the backend scribes — the low-level draft
 *      edit is the ONE canonical {@see \JesseGall\CodeCommandments\Scribes\Writer}'s job; a scribe
 *      describes WHAT to change (`replace`/`stampAttribute`/`ensureImport`/`insertAt`/`dropModifier`/
 *      `deleteStatementLine`), never re-invents indentation, keyword-hunting or attribute insertion.
 *      A missing write op is a method to ADD to the Writer, not a raw edit in the scribe.
 *
 * The ONLY exemptions are the genuine LEXERS (where char/delimiter scanning IS the job), the
 * byte-offset utility {@see \JesseGall\CodeCommandments\Scribes\Span} (newline/offset math), and the
 * compiler-diagnostic reader {@see \JesseGall\CodeCommandments\Vue\Oracle\TscDiagnostics} — which
 * scans `vue-tsc`'s flat log OUTPUT, not source code, so text scanning is likewise its whole job.
 * Prefix/suffix classification (`str_starts_with`/`str_ends_with` on a name/FQCN/path) is
 * allowed — it reads a known token, it doesn't parse the source.
 */
final class NoRegexInParsingLayerTest extends TestCase
{
    private const array REGEX = ['preg_match', 'preg_match_all', 'preg_replace', 'preg_replace_callback', 'preg_split', 'preg_quote'];

    private const array SCAN = ['strpos', 'strrpos', 'strstr'];

    private const array CHAR_LEXING = ['ctype_alnum', 'ctype_alpha', 'ctype_digit', 'ctype_xdigit', 'ctype_space', 'ctype_upper', 'ctype_lower'];

    /** The low-level draft edit — the canonical Writer's job alone; a backend scribe composes the Writer, never this. */
    private const array REWRITE = ['->edit'];

    /** Lexers, the offset util, and the compiler-output reader — the only places raw scanning is the job. */
    private const array LEXERS = ['Tokenizer.php', 'Sfc.php', 'Attributes.php', 'Script.php', 'Interpolation.php', 'Lexer.php', 'Parser.php', 'Span.php', 'TscDiagnostics.php'];

    public function test_no_regex_in_the_detection_scribe_or_engine_layer(): void
    {
        $this->assertNoneOf(self::REGEX, $this->phpIn('Detectors', 'Scribes', 'Vue'), 'regex over code — compose the AST, don\'t scrape it');
    }

    public function test_no_handrolled_source_scanning_in_the_detection_scribe_or_engine_layer(): void
    {
        $this->assertNoneOf(self::SCAN, $this->phpIn('Detectors', 'Scribes', 'Vue'), 'hand-rolled source scanning — the AST knows positions; query it or add a Span primitive');
    }

    public function test_no_handrolled_char_lexing_in_the_backend_layer(): void
    {
        $this->assertNoneOf(self::CHAR_LEXING, $this->phpIn('Detectors/Backend', 'Scribes/Backend'), 'hand-rolled char lexing — the backend has php-parser + Span; never build a token / skip whitespace by hand');
    }

    public function test_backend_scribes_rewrite_through_the_canonical_writer(): void
    {
        $this->assertNoneOf(self::REWRITE, $this->phpIn('Scribes/Backend'), 'bespoke rewriting — compose the one Writer (replace/stampAttribute/ensureImport/insertAt/dropModifier/deleteStatementLine); add a Writer method for a missing op, never a raw ->edit');
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
