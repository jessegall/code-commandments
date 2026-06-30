<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Architecture;

use PHPUnit\Framework\TestCase;

/**
 * The cardinal rule, enforced: the layer that UNDERSTANDS code — every detector, the
 * expression and script parsers, the component-extract scribe — reads it through the
 * AST / tokeniser, NEVER a regex. (Genuine text/delimiter scanning lives in the
 * tokenisers themselves and is deliberately not in this set.) A `preg_*` creeping into
 * any file below fails this test, so "use a regex to parse the code" can't come back.
 */
final class NoRegexInParsingLayerTest extends TestCase
{
    public function test_the_parsing_and_detection_layer_contains_no_regex(): void
    {
        $offenders = [];

        foreach ($this->files() as $file) {
            $source = (string) file_get_contents($file);

            foreach (['preg_match', 'preg_match_all', 'preg_replace', 'preg_replace_callback', 'preg_split'] as $regex) {
                if (str_contains($source, $regex . '(')) {
                    $offenders[] = substr($file, strpos($file, 'src/') ?: 0) . " uses {$regex}";
                }
            }
        }

        $this->assertSame([], $offenders, "regex in the AST/detection layer — parse it, don't scrape it:\n" . implode("\n", $offenders));
    }

    /**
     * @return list<string>
     */
    private function files(): array
    {
        $root = __DIR__ . '/../../src';

        return [
            ...glob("{$root}/Detectors/Backend/*.php") ?: [],
            ...glob("{$root}/Detectors/Frontend/*.php") ?: [],
            ...glob("{$root}/Vue/Expr/*.php") ?: [],
            "{$root}/Vue/Script.php",
            "{$root}/Scribes/Frontend/ExtractComponentScribe.php",
        ];
    }
}
