<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Scribes\Backend;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Backend\Detector;
use JesseGall\CodeCommandments\Scribes\NeedsCodebase;
use JesseGall\CodeCommandments\Scribes\RepentScribe;
use PHPUnit\Framework\TestCase;

/**
 * Shared harness for a backend (finding-driven) {@see RepentScribe} test — the backend
 * twin of the frontend scribe tests. A subclass names its {@see Detector} + {@see RepentScribe};
 * the base wires the pipeline (`detector->find()` → `scribe->rewrite()`), lints the output
 * with `php -l`, and proves stability.
 *
 * Every scribe gets THREE tests built on this base: it FIXES the sin, it does NOT OVERSHOOT
 * (a look-alike sibling is left byte-identical), and it is IDEMPOTENT (the sin no longer
 * fires and a second pass is a no-op).
 */
abstract class ScribeTestCase extends TestCase
{
    abstract protected function detector(): Detector;

    abstract protected function scribe(): RepentScribe;

    /**
     * Run the detector + scribe over $php and return the rewritten source (or the input
     * unchanged when nothing was rewritten).
     */
    protected function fix(string $php): string
    {
        $rewrites = $this->applyScribe($php);

        return $rewrites === [] ? $php : (string) reset($rewrites);
    }

    /**
     * @return list<\JesseGall\CodeCommandments\Ast\NodeMatch>
     */
    protected function findings(string $php): array
    {
        return $this->detector()->find(Codebase::fromString($php));
    }

    protected function rewrote(string $php): bool
    {
        return $this->applyScribe($php) !== [];
    }

    /**
     * Run the detector + scribe over one in-memory file, wiring whole-codebase context to a
     * {@see NeedsCodebase} scribe exactly as the {@see \JesseGall\CodeCommandments\Scribes\Backend\DetectorStep}
     * does at runtime.
     *
     * @return array<string, string>
     */
    private function applyScribe(string $php): array
    {
        $codebase = Codebase::fromString($php);
        $scribe = $this->scribe();

        if ($scribe instanceof NeedsCodebase) {
            $scribe->withCodebase($codebase);
        }

        return $scribe->rewrite($this->detector()->find($codebase));
    }

    /**
     * The full fix + guarantees in one call: the result parses, the sin no longer fires,
     * and a second pass changes nothing (idempotent). Returns the fixed source to assert on.
     */
    protected function fixStable(string $php): string
    {
        $fixed = $this->fix($php);

        $this->assertParses($fixed);
        $this->assertSame([], $this->findings($fixed), 'the sin still fires after the fix');
        $this->assertSame($fixed, $this->fix($fixed), 'the fix is not idempotent — a second pass changed the source');

        return $fixed;
    }

    protected function assertParses(string $php): void
    {
        $file = tempnam(sys_get_temp_dir(), 'cc-scribe') . '.php';
        file_put_contents($file, $php);
        exec('php -l ' . escapeshellarg($file) . ' 2>&1', $out, $status);
        @unlink($file);

        $this->assertSame(0, $status, "rewritten source does not parse:\n" . implode("\n", $out) . "\n\n" . $php);
    }
}
