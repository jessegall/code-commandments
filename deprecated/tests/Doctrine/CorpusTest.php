<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Doctrine;

use JesseGall\CodeCommandments\Contracts\NeedsCodebaseIndex;
use JesseGall\CodeCommandments\Support\CallGraph\CodebaseIndex;
use JesseGall\CodeCommandments\Support\ConfigLoader;
use JesseGall\CodeCommandments\Support\ProphetRegistry;
use PHPUnit\Framework\TestCase;

/**
 * v3 integration corpus (Phase 1). Runs the FULL backend registry over messy/golden
 * twins. Assertion (a): properly-refactored "golden" code yields ZERO findings across
 * ALL backend prophets — the fixed point that catches any prophet over-firing on
 * clean code. Assertions (b) no-overlapping-autofix / (c) cause-first / (d)
 * repent-convergence follow once the corpus is seeded with messy twins.
 *
 * Seeded from the invariant-root-cause example twins (initial = messy, final = golden).
 * Frontend is out of scope for the v3 doctrine phase.
 */
class CorpusTest extends TestCase
{
    private const EXAMPLES = __DIR__ . '/../../invariant-root-cause/examples';

    private const CORPUS = __DIR__ . '/../Fixtures/corpus';

    private const CONFIG = __DIR__ . '/../../config/commandments.php';

    /**
     * @dataProvider goldenScenarios
     */
    public function test_golden_twin_is_clean_across_all_backend_prophets(string $dir): void
    {
        $findings = $this->backendFindings($dir);

        $this->assertSame([], $findings, sprintf(
            "Golden twin '%s' must be silent across ALL backend prophets, but %d fired:\n  %s",
            basename(dirname($dir)),
            count($findings),
            implode("\n  ", $findings),
        ));
    }

    /**
     * Every golden twin must be SILENT across the full backend registry. Sources:
     * the dedicated v3 corpus (`tests/Fixtures/corpus/<subsystem>/golden`), plus the
     * subset of invariant-root-cause `final` twins that are universally clean. The
     * three remaining invariant seeds (01 enum-dispatch, 05 genuine-vs-invariant, 06
     * notifications) are golden only for the 9-prophet root-cause family (the full
     * registry still flags undocumented cases / un-based registries on them), so they
     * are excluded until cleaned.
     *
     * @return iterable<string, array{string}>
     */
    public static function goldenScenarios(): iterable
    {
        foreach ((array) glob(self::CORPUS . '/*/golden', GLOB_ONLYDIR) as $dir) {
            if (self::isJudgmentCorpus(basename(dirname($dir)))) {
                continue;
            }

            yield 'corpus:' . basename(dirname($dir)) => [$dir];
        }

        foreach (['example-02-registry-contract', 'example-03-null-object', 'example-04-swallowed-notfound'] as $name) {
            yield $name => [self::EXAMPLES . '/' . $name . '/final'];
        }
    }

    /**
     * The messy twin of every corpus subsystem must LIGHT UP — a before/after pair
     * is only useful if the "before" actually trips prophets.
     *
     * @dataProvider messyScenarios
     */
    public function test_messy_twin_lights_up(string $dir): void
    {
        $this->assertNotSame([], $this->backendFindings($dir), sprintf(
            "Messy twin '%s' should trip prophets but was silent.",
            basename(dirname($dir)),
        ));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function messyScenarios(): iterable
    {
        foreach ((array) glob(self::CORPUS . '/*/messy', GLOB_ONLYDIR) as $dir) {
            if (self::isJudgmentCorpus(basename(dirname($dir)))) {
                continue;
            }

            yield 'corpus:' . basename(dirname($dir)) => [$dir];
        }
    }

    /**
     * The Option-vs-null slices are a JUDGMENT corpus — they exercise specific
     * Option-prophet behaviour (when an Option is justified vs over-engineering),
     * not universal cleanliness. Their goldens are idiomatic but not silent across
     * EVERY prophet, and the over-engineering in a messy twin is deliberately subtle
     * — so the universal silent/lights-up net does not fit them. They have their own
     * dedicated test ({@see OptionCorpusTest}).
     */
    private static function isJudgmentCorpus(string $slug): bool
    {
        return str_starts_with($slug, 'option-') || str_starts_with($slug, 'null-');
    }

    /**
     * @return list<string>  "Prophet @ file:line — message" offenders
     */
    private function backendFindings(string $dir): array
    {
        $files = array_values(array_filter((array) glob($dir . '/*.php')));
        $index = CodebaseIndex::build($files);

        $registry = new ProphetRegistry();
        $config = ConfigLoader::load(self::CONFIG);
        $backend = $config['scrolls']['backend'] ?? [];
        $registry->registerMany('backend', $backend['prophets'] ?? []);
        $registry->setScrollConfig('backend', $backend);

        $prophets = $registry->getProphets('backend');

        foreach ($prophets as $prophet) {
            if ($prophet instanceof NeedsCodebaseIndex) {
                $prophet->setCodebaseIndex($index);
            }
        }

        $out = [];

        foreach ($files as $file) {
            $content = (string) file_get_contents($file);

            foreach ($prophets as $prophet) {
                $judgment = $prophet->judge($file, $content);

                foreach ([...$judgment->sins, ...$judgment->warnings] as $finding) {
                    $out[] = sprintf(
                        '%s @ %s:%s — %s',
                        class_basename($prophet),
                        basename($file),
                        $finding->line ?? '?',
                        $finding->message,
                    );
                }
            }
        }

        return $out;
    }
}
