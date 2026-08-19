<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Detectors\Backend\DanglingDocReferenceDetector;
use JesseGall\CodeCommandments\Detectors\Backend\ArchaeologyCommentDetector;
use JesseGall\CodeCommandments\Detectors\CrossFileSet;
use JesseGall\CodeCommandments\Detectors\Catalog;
use JesseGall\CodeCommandments\Detector;
use JesseGall\CodeCommandments\Workspace;
use PHPUnit\Framework\TestCase;

/**
 * Which detectors read beyond the file in hand, worked out from the SOURCE rather than from a marker
 * somebody remembered to apply — the question a per-file check and a per-file cache both turn on.
 */
final class CrossFileSetTest extends TestCase
{
    private static ?CrossFileSet $set = null;

    private static string $custom = '';

    public static function setUpBeforeClass(): void
    {
        // A consumer's own detector, discovered by FILE like `.commandments/custom/` is — it must be
        // classified beside the shipped ones, or a project's rule would be trusted on no evidence.
        self::$custom = sys_get_temp_dir() . '/cc-custom-' . uniqid('', true) . '.php';
        file_put_contents(self::$custom, <<<'PHP'
            <?php

            namespace Consumer\Commandments;

            use JesseGall\CodeCommandments\Ast\Codebase;
            use JesseGall\CodeCommandments\Detector;
            use JesseGall\CodeCommandments\Sins\Sin;

            final class LocalOnlyDetector implements Detector
            {
                public function sin(): Sin
                {
                    throw new \LogicException('never asked');
                }

                public function find(Codebase $codebase): array
                {
                    return $codebase->whereClass()->get();
                }
            }

            final class WorldReadingDetector implements Detector
            {
                public function sin(): Sin
                {
                    throw new \LogicException('never asked');
                }

                public function find(Codebase $codebase): array
                {
                    return $codebase->declarationMatch('Consumer\\Thing') === null ? [] : ['x'];
                }
            }
            PHP);

        self::$set = CrossFileSet::over(Codebase::scan([__DIR__ . '/../../src', self::$custom]));
    }

    public static function tearDownAfterClass(): void
    {
        @unlink(self::$custom);
    }

    public function test_a_detector_that_asks_the_codebase_about_another_file_is_in_the_set(): void
    {
        // It resolves a `{@see}` against every declaration in the tree — the very bug that put it
        // behind `WholeTree`.
        $this->assertTrue(self::$set->has(new DanglingDocReferenceDetector()));
    }

    public function test_a_detector_that_only_reads_the_file_in_hand_is_not(): void
    {
        // A history comment is a fact about the lines in front of it and nothing else.
        $this->assertFalse(self::$set->has(new ArchaeologyCommentDetector()));
    }

    public function test_a_consumer_s_own_detector_is_classified_from_its_source_too(): void
    {
        require_once self::$custom;

        $this->assertFalse(self::$set->has($this->asDetector('Consumer\Commandments\LocalOnlyDetector')));
        $this->assertTrue(self::$set->has($this->asDetector('Consumer\Commandments\WorldReadingDetector')));
    }

    public function test_a_detector_the_source_does_not_account_for_is_treated_as_reading_the_world(): void
    {
        // Silence is not evidence of locality. A verdict we cannot PROVE local must be keyed as the
        // world's, or a cache would serve a stale answer for the one rule nobody could see.
        $unknown = new class implements Detector
        {
            public function sin(): \JesseGall\CodeCommandments\Sins\Sin
            {
                throw new \LogicException('never asked');
            }

            public function find(Codebase $codebase): array
            {
                return [];
            }
        };

        $this->assertTrue(self::$set->has($unknown));
    }

    public function test_every_rule_marked_whole_tree_is_found_to_read_beyond_the_file(): void
    {
        $marked = array_filter(Catalog::all(), static fn (object $d): bool => $d instanceof \JesseGall\CodeCommandments\WholeTree);

        $this->assertNotSame([], $marked);

        foreach ($marked as $detector) {
            $this->assertTrue(self::$set->has($detector), $detector::class . ' is marked WholeTree but reads no further than the file');
        }
    }

    public function test_a_reread_works_the_answer_out_again_whatever_the_stamp_says(): void
    {
        // The stamp cannot see an edit to a file the package already had — a directory's mtime does
        // not move for a write inside one of its subdirectories. So `sync` does not consult it.
        $root = sys_get_temp_dir() . '/cc-reread-' . uniqid('', true);
        @mkdir($root . '/.commandments', 0777, true);
        $workspace = Workspace::at($root);

        CrossFileSet::forProject($workspace);

        $file = $workspace->shared('cross-file.json');
        $stored = json_decode((string) file_get_contents($file), true);
        $stamp = $stored['stamp'];
        $stored['beyond'] = ['Stale\\Answer' => true];
        file_put_contents($file, (string) json_encode($stored));

        CrossFileSet::reread($workspace);
        $written = json_decode((string) file_get_contents($file), true);

        $this->assertSame($stamp, $written['stamp'], 'the stamp had not moved');
        $this->assertArrayNotHasKey('Stale\\Answer', $written['beyond'], 'and it was worked out again regardless');

        exec('rm -rf ' . escapeshellarg($root));
    }

    private function asDetector(string $class): Detector
    {
        /** @var Detector $instance */
        $instance = new $class();

        return $instance;
    }
}
