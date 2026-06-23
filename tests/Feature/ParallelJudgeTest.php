<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Feature;

use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Scanners\GenericFileScanner;
use JesseGall\CodeCommandments\Support\Concurrency\ForkPool;
use JesseGall\CodeCommandments\Support\Environment;
use JesseGall\CodeCommandments\Support\ProphetRegistry;
use JesseGall\CodeCommandments\Support\ScrollManager;
use JesseGall\CodeCommandments\Tests\Fixtures\Prophets\CrossCountingProphet;
use JesseGall\CodeCommandments\Tests\Fixtures\Prophets\ProfileMarkerProphet;
use PHPUnit\Framework\TestCase;

/**
 * The invariant that makes parallel judging safe: a forked judge must produce
 * EXACTLY the same findings as a sequential one. Includes a cross-file prophet so
 * the copy-on-write index inheritance is exercised under fork.
 */
class ParallelJudgeTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir() . '/cc-parallel-' . uniqid();
        mkdir($this->dir, 0755, true);
        Environment::setBasePath($this->dir);
    }

    protected function tearDown(): void
    {
        shell_exec('rm -rf ' . escapeshellarg($this->dir));
        parent::tearDown();
    }

    private function manager(int $workers): ScrollManager
    {
        $registry = new ProphetRegistry();
        $registry->registerMany('test', [ProfileMarkerProphet::class, CrossCountingProphet::class]);
        $registry->setScrollConfig('test', ['path' => $this->dir, 'extensions' => ['php']]);

        $manager = new ScrollManager($registry, new GenericFileScanner());
        $manager->setUseCache(false); // every file is a miss → all judged (and forked)
        $manager->setWorkers($workers);

        return $manager;
    }

    /**
     * Normalise a judge result into a sorted list of `file|prophet|kind|message`
     * lines, so two runs can be compared regardless of ordering.
     *
     * @return list<string>
     */
    private function signature(\Illuminate\Support\Collection $results): array
    {
        $lines = [];

        foreach ($results as $filePath => $judgments) {
            $name = basename($filePath);

            foreach ($judgments as $prophet => $judgment) {
                /** @var Judgment $judgment */
                foreach ($judgment->sins as $sin) {
                    $lines[] = "{$name}|{$prophet}|sin|{$sin->message}";
                }

                foreach ($judgment->warnings as $warning) {
                    $lines[] = "{$name}|{$prophet}|warning|{$warning->message}";
                }
            }
        }

        sort($lines);

        return $lines;
    }

    public function test_parallel_judge_matches_sequential(): void
    {
        if (! ForkPool::isAvailable()) {
            $this->markTestSkipped('pcntl/fork unavailable on this platform.');
        }

        $files = [
            'A.php' => 'SIN_ME',
            'B.php' => 'WARN_ME',
            'C.php' => 'clean',
            'D.php' => 'SIN_ME WARN_ME',
            'E.php' => 'FLAG_ME',         // cross-file prophet flags this
            'F.php' => 'clean',
            'G.php' => 'SIN_ME FLAG_ME',
        ];

        foreach ($files as $name => $body) {
            file_put_contents($this->dir . '/' . $name, "<?php\n// {$body}\n");
        }

        $sequential = $this->signature($this->manager(1)->judgeScroll('test'));
        $parallel = $this->signature($this->manager(4)->judgeScroll('test'));

        $this->assertNotEmpty($sequential, 'the fixtures should produce findings');
        $this->assertSame($sequential, $parallel, 'a forked judge must match a sequential one exactly');
    }
}
