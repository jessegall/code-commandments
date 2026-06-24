<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Doctrine;

use JesseGall\CodeCommandments\Prophets\Backend\NoOptionOveruseProphet;
use JesseGall\CodeCommandments\Support\CallGraph\CodebaseIndex;
use PHPUnit\Framework\TestCase;

/**
 * The Option-vs-null JUDGMENT corpus: blind-authored multi-file slices where the
 * verdict (Option earns it, or null is right and Option is over-engineering) is
 * driven by the CALL SITES. Asserts the call-site-aware over-engineering detector
 * (NoOptionOveruse) agrees with that judgment — fires on a needless Option, stays
 * silent on a justified one.
 */
class OptionCorpusTest extends TestCase
{
    private const CORPUS = __DIR__ . '/../Fixtures/corpus';

    /**
     * @dataProvider overEngineeredSlices
     */
    public function test_flags_over_engineered_option(string $messyDir): void
    {
        $this->assertGreaterThan(0, $this->overuseFindings($messyDir),
            basename(dirname($messyDir)) . ': a needless Option (callers only unwrap it) should be flagged.');
    }

    /**
     * @dataProvider justifiedGoldens
     */
    public function test_silent_on_a_justified_option(string $goldenDir): void
    {
        $this->assertSame(0, $this->overuseFindings($goldenDir),
            basename(dirname($goldenDir)) . ': an Option whose callers chain/branch on it must NOT be flagged.');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function overEngineeredSlices(): iterable
    {
        // null-right-framework-idiom is a known residual: its single ->map is
        // replaceable by a nullsafe, which the detector deliberately leaves alone.
        foreach (['null-right-cache-miss', 'null-right-single-caller-helper'] as $slug) {
            yield $slug => [self::CORPUS . '/' . $slug . '/messy'];
        }
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function justifiedGoldens(): iterable
    {
        foreach ((array) glob(self::CORPUS . '/option-justified-*/golden', GLOB_ONLYDIR) as $dir) {
            yield basename(dirname($dir)) => [$dir];
        }
    }

    private function overuseFindings(string $dir): int
    {
        $files = array_values(array_filter((array) glob($dir . '/*.php')));
        $prophet = new NoOptionOveruseProphet;
        $prophet->setCodebaseIndex(CodebaseIndex::build($files));

        $count = 0;

        foreach ($files as $file) {
            $judgment = $prophet->judge($file, (string) file_get_contents($file));
            $count += count($judgment->sins) + count($judgment->warnings);
        }

        return $count;
    }
}
