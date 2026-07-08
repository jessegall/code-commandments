<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Architecture;

use PHPUnit\Framework\TestCase;

/**
 * The reusability rule, enforced — CLAUDE.md's cardinal principle for the DETECTION layer: a detector
 * COMPOSES the fluent engine (`Codebase` selectors → `Query` filters → `AstNode`/`NodeMatch` predicates),
 * it does not hand-walk the AST. When a predicate is missing, you ADD it to the engine (reusably) so every
 * detector shares it — you do NOT `new NodeFinder()` inside the detector or hoard a pile of `PhpParser\Node`
 * type juggling there. Both are the same mistake, twice made, now guarded:
 *
 *   1. NO `NodeFinder` in a detector — walking the tree is engine work; it belongs on `AstNode`/`Codebase`/
 *      a `Support` analysis, where it is written once and reused. (Scribes may still walk, hence detectors only.)
 *   2. A detector imports at most {@see self::MAX_NODE_IMPORTS} `PhpParser\` symbols — more means it is
 *      reasoning over raw nodes instead of composing the DSL; push those predicates onto the engine.
 */
final class DetectorsComposeTheEngineTest extends TestCase
{
    /** The most `PhpParser\` imports a thin detector needs; above it, move the node logic onto the engine. */
    private const int MAX_NODE_IMPORTS = 10;

    public function test_no_detector_hand_walks_the_ast_with_a_nodefinder(): void
    {
        $offenders = [];

        foreach ($this->detectors() as $file) {
            if (str_contains((string) file_get_contents($file), 'NodeFinder')) {
                $offenders[] = $this->relative($file);
            }
        }

        sort($offenders);

        $this->assertSame([], $offenders, "A detector hand-walks the AST with NodeFinder — move the walk onto a reusable engine method (AstNode/Codebase/Support) and compose it:\n" . implode("\n", $offenders));
    }

    public function test_no_detector_hoards_raw_node_types(): void
    {
        $offenders = [];

        foreach ($this->detectors() as $file) {
            $imports = preg_match_all('/^use PhpParser\\\\/m', (string) file_get_contents($file));

            if ($imports > self::MAX_NODE_IMPORTS) {
                $offenders[] = $this->relative($file) . " imports {$imports} PhpParser symbols";
            }
        }

        sort($offenders);

        $this->assertSame([], $offenders, "A detector reasons over raw nodes instead of composing the engine — extract the predicates onto AstNode/Query so they are reused (≤ " . self::MAX_NODE_IMPORTS . " PhpParser imports):\n" . implode("\n", $offenders));
    }

    /**
     * @return list<string>
     */
    private function detectors(): array
    {
        $base = __DIR__ . '/../../src/Detectors';
        $files = [];

        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($base, \FilesystemIterator::SKIP_DOTS));

        foreach ($iterator as $file) {
            if ($file->isFile() && str_ends_with($file->getFilename(), 'Detector.php')) {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }

    private function relative(string $file): string
    {
        return substr($file, (int) (strpos($file, 'src/') ?: 0));
    }
}
