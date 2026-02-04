<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Support;

use JesseGall\CodeCommandments\Commandments\BaseCommandment;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Scanners\GenericFileScanner;
use JesseGall\CodeCommandments\Support\ProphetRegistry;
use JesseGall\CodeCommandments\Support\ScrollManager;
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
    }

    public function test_prophet_exclusion_skips_matching_files(): void
    {
        $prophet = new class extends BaseCommandment {
            public bool $wasJudged = false;
            public function applicableExtensions(): array { return ['php']; }
            public function description(): string { return 'Test'; }
            public function detailedDescription(): string { return 'Test prophet'; }
            public function judge(string $filePath, string $content): Judgment
            {
                $this->wasJudged = true;
                return $this->righteous();
            }
        };

        // Configure the prophet with an exclusion that matches this test file
        $prophet->configure(['exclude' => ['ScrollManagerTest.php']]);

        $this->app->bind('test_prophet', fn () => $prophet);

        $this->registry->register('test', 'test_prophet');
        $this->registry->setScrollConfig('test', [
            'path' => __DIR__,
            'extensions' => ['php'],
        ]);

        // Judge this test file which matches the exclusion pattern
        $results = $this->scrollManager->judgeFile('test', __FILE__);

        $this->assertTrue($results->isEmpty());
        $this->assertFalse($prophet->wasJudged);
    }

    public function test_prophet_exclusion_allows_non_matching_files(): void
    {
        $prophet = new class extends BaseCommandment {
            public bool $wasJudged = false;
            public function applicableExtensions(): array { return ['php']; }
            public function description(): string { return 'Test'; }
            public function detailedDescription(): string { return 'Test prophet'; }
            public function judge(string $filePath, string $content): Judgment
            {
                $this->wasJudged = true;
                return $this->righteous();
            }
        };

        // Configure the prophet with an exclusion that does NOT match this test file
        $prophet->configure(['exclude' => ['SomeOtherFile.php']]);

        $this->app->bind('test_prophet', fn () => $prophet);

        $this->registry->register('test', 'test_prophet');
        $this->registry->setScrollConfig('test', [
            'path' => __DIR__,
            'extensions' => ['php'],
        ]);

        // Judge this test file which does NOT match the exclusion pattern
        $results = $this->scrollManager->judgeFile('test', __FILE__);

        $this->assertFalse($results->isEmpty());
        $this->assertTrue($prophet->wasJudged);
    }

    public function test_prophet_without_exclusion_judges_all_files(): void
    {
        $prophet = new class extends BaseCommandment {
            public bool $wasJudged = false;
            public function applicableExtensions(): array { return ['php']; }
            public function description(): string { return 'Test'; }
            public function detailedDescription(): string { return 'Test prophet'; }
            public function judge(string $filePath, string $content): Judgment
            {
                $this->wasJudged = true;
                return $this->righteous();
            }
        };

        // No exclusion configured
        $this->app->bind('test_prophet', fn () => $prophet);

        $this->registry->register('test', 'test_prophet');
        $this->registry->setScrollConfig('test', [
            'path' => __DIR__,
            'extensions' => ['php'],
        ]);

        $results = $this->scrollManager->judgeFile('test', __FILE__);

        $this->assertFalse($results->isEmpty());
        $this->assertTrue($prophet->wasJudged);
    }

    public function test_multiple_prophets_with_different_exclusions(): void
    {
        $prophet1 = new class extends BaseCommandment {
            public bool $wasJudged = false;
            public function applicableExtensions(): array { return ['php']; }
            public function description(): string { return 'Test 1'; }
            public function detailedDescription(): string { return 'Test prophet 1'; }
            public function judge(string $filePath, string $content): Judgment
            {
                $this->wasJudged = true;
                return $this->righteous();
            }
        };

        $prophet2 = new class extends BaseCommandment {
            public bool $wasJudged = false;
            public function applicableExtensions(): array { return ['php']; }
            public function description(): string { return 'Test 2'; }
            public function detailedDescription(): string { return 'Test prophet 2'; }
            public function judge(string $filePath, string $content): Judgment
            {
                $this->wasJudged = true;
                return $this->righteous();
            }
        };

        // Prophet 1 excludes this test file, prophet 2 does not
        $prophet1->configure(['exclude' => ['ScrollManagerTest.php']]);
        $prophet2->configure(['exclude' => ['SomeOtherFile.php']]);

        $this->app->bind('test_prophet_1', fn () => $prophet1);
        $this->app->bind('test_prophet_2', fn () => $prophet2);

        $this->registry->register('test', 'test_prophet_1');
        $this->registry->register('test', 'test_prophet_2');
        $this->registry->setScrollConfig('test', [
            'path' => __DIR__,
            'extensions' => ['php'],
        ]);

        $results = $this->scrollManager->judgeFile('test', __FILE__);

        // Only prophet 2 should have judged
        $this->assertCount(1, $results);
        $this->assertFalse($prophet1->wasJudged);
        $this->assertTrue($prophet2->wasJudged);
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
