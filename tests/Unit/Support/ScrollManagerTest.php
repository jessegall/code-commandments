<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Support;

use JesseGall\CodeCommandments\Commandments\BaseCommandment;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Scanners\GenericFileScanner;
use JesseGall\CodeCommandments\Support\ProphetRegistry;
use JesseGall\CodeCommandments\Support\ScrollManager;
use JesseGall\CodeCommandments\Tests\Fixtures\Prophets\ExcludingTestProphet;
use JesseGall\CodeCommandments\Tests\Fixtures\Prophets\ExcludingTestProphet2;
use JesseGall\CodeCommandments\Tests\TestCase;

class ScrollManagerTest extends TestCase
{
    private ProphetRegistry $registry;
    private ScrollManager $scrollManager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->registry = new ProphetRegistry();
        $this->scrollManager = new ScrollManager($this->registry, new GenericFileScanner());

        ExcludingTestProphet::resetState();
        ExcludingTestProphet2::resetState();
    }

    public function test_prophet_exclusion_skips_matching_files(): void
    {
        $this->registry->registerMany('test', [
            ExcludingTestProphet::class => ['exclude' => ['ScrollManagerTest.php']],
        ]);
        $this->registry->setScrollConfig('test', [
            'path' => __DIR__,
            'extensions' => ['php'],
        ]);

        // Judge this test file which matches the exclusion pattern
        $results = $this->scrollManager->judgeFile('test', __FILE__);

        $this->assertTrue($results->isEmpty());
        $this->assertFalse(ExcludingTestProphet::$wasJudged);
    }

    public function test_prophet_exclusion_allows_non_matching_files(): void
    {
        $this->registry->registerMany('test', [
            ExcludingTestProphet::class => ['exclude' => ['SomeOtherFile.php']],
        ]);
        $this->registry->setScrollConfig('test', [
            'path' => __DIR__,
            'extensions' => ['php'],
        ]);

        // Judge this test file which does NOT match the exclusion pattern
        $results = $this->scrollManager->judgeFile('test', __FILE__);

        $this->assertFalse($results->isEmpty());
        $this->assertTrue(ExcludingTestProphet::$wasJudged);
    }

    public function test_prophet_without_exclusion_judges_all_files(): void
    {
        $this->registry->register('test', ExcludingTestProphet::class);
        $this->registry->setScrollConfig('test', [
            'path' => __DIR__,
            'extensions' => ['php'],
        ]);

        $results = $this->scrollManager->judgeFile('test', __FILE__);

        $this->assertFalse($results->isEmpty());
        $this->assertTrue(ExcludingTestProphet::$wasJudged);
    }

    public function test_multiple_prophets_with_different_exclusions(): void
    {
        $this->registry->registerMany('test', [
            ExcludingTestProphet::class => ['exclude' => ['ScrollManagerTest.php']],
            ExcludingTestProphet2::class => ['exclude' => ['SomeOtherFile.php']],
        ]);
        $this->registry->setScrollConfig('test', [
            'path' => __DIR__,
            'extensions' => ['php'],
        ]);

        $results = $this->scrollManager->judgeFile('test', __FILE__);

        // Only prophet 2 should have judged
        $this->assertCount(1, $results);
        $this->assertFalse(ExcludingTestProphet::$wasJudged);
        $this->assertTrue(ExcludingTestProphet2::$wasJudged);
    }

    public function test_judge_files_honors_glob_exclude_patterns(): void
    {
        $tempDir = sys_get_temp_dir() . '/cc-scroll-' . uniqid();
        mkdir($tempDir);

        $dts = $tempDir . '/types.d.ts';
        $php = $tempDir . '/user.php';

        file_put_contents($dts, "// typescript declaration\n");
        file_put_contents($php, "<?php\n");

        try {
            $this->registry->register('test', ExcludingTestProphet::class);
            $this->registry->setScrollConfig('test', [
                'path' => $tempDir,
                'extensions' => ['php', 'd.ts', 'ts'],
                'exclude' => ['*.d.ts'],
            ]);

            $results = $this->scrollManager->judgeFiles('test', [$dts, $php]);

            $this->assertTrue($results->has($php), 'Expected user.php to be judged');
            $this->assertFalse($results->has($dts), 'Expected types.d.ts to be excluded via glob');
        } finally {
            @unlink($dts);
            @unlink($php);
            @rmdir($tempDir);
        }
    }

    public function test_judge_files_honors_plain_string_exclude(): void
    {
        $tempDir = sys_get_temp_dir() . '/cc-scroll-' . uniqid();
        mkdir($tempDir);
        mkdir($tempDir . '/Console');

        $kernel = $tempDir . '/Console/Kernel.php';
        $other = $tempDir . '/Foo.php';

        file_put_contents($kernel, "<?php\n");
        file_put_contents($other, "<?php\n");

        try {
            $this->registry->register('test', ExcludingTestProphet::class);
            $this->registry->setScrollConfig('test', [
                'path' => $tempDir,
                'extensions' => ['php'],
                'exclude' => ['Console/Kernel.php'],
            ]);

            $results = $this->scrollManager->judgeFiles('test', [$kernel, $other]);

            $this->assertTrue($results->has($other));
            $this->assertFalse($results->has($kernel));
        } finally {
            @unlink($kernel);
            @unlink($other);
            @rmdir($tempDir . '/Console');
            @rmdir($tempDir);
        }
    }

    public function test_base_commandment_get_excluded_paths_returns_config_value(): void
    {
        $prophet = new class extends BaseCommandment {
            public function applicableExtensions(): array { return ['php']; }
            public function description(): string { return 'Test'; }
            public function detailedDescription(): string { return 'Test prophet'; }
            public function judge(string $filePath, string $content): Judgment
            {
                return $this->righteous();
            }
        };

        $this->assertEquals([], $prophet->getExcludedPaths());

        $prophet->configure(['exclude' => ['path/to/exclude', 'another/path']]);

        $this->assertEquals(['path/to/exclude', 'another/path'], $prophet->getExcludedPaths());
    }
}
