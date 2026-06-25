<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Support;

use JesseGall\CodeCommandments\Support\ConfigSyncer;
use JesseGall\CodeCommandments\Support\Environment;
use JesseGall\CodeCommandments\Tests\TestCase;

class ConfigSyncerTest extends TestCase
{
    private ConfigSyncer $syncer;

    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->syncer = new ConfigSyncer();
        $this->tempDir = sys_get_temp_dir() . '/commandments-test-' . uniqid();
        mkdir($this->tempDir, 0755, true);
        Environment::setBasePath($this->tempDir);
    }

    protected function tearDown(): void
    {
        $this->cleanTempDir($this->tempDir);
        parent::tearDown();
    }

    public function test_adds_missing_prophets_to_backend_scroll(): void
    {
        $configPath = $this->createConfigFile([
            'backend' => [
                'extensions' => ['php'],
                'prophets' => [],
            ],
        ]);

        $result = $this->syncer->sync($configPath);

        $this->assertNotEmpty($result['added']);
        $this->assertNotEmpty($result['source']);

        // All added prophets should be for the backend scroll
        foreach ($result['added'] as $entry) {
            $this->assertEquals('backend', $entry['scroll']);
            $this->assertStringContainsString('Prophets\\Backend\\', $entry['class']);
        }
    }

    public function test_adds_missing_prophets_to_frontend_scroll(): void
    {
        $configPath = $this->createConfigFile([
            'frontend' => [
                'extensions' => ['vue', 'ts', 'js'],
                'prophets' => [],
            ],
        ]);

        $result = $this->syncer->sync($configPath);

        $this->assertNotEmpty($result['added']);

        foreach ($result['added'] as $entry) {
            $this->assertEquals('frontend', $entry['scroll']);
            $this->assertStringContainsString('Prophets\\Frontend\\', $entry['class']);
        }
    }

    public function test_does_not_duplicate_existing_prophets(): void
    {
        // Get all backend prophets first
        $configPath = $this->createConfigFile([
            'backend' => [
                'extensions' => ['php'],
                'prophets' => [],
            ],
        ]);

        $firstResult = $this->syncer->sync($configPath);

        // Write the synced config
        file_put_contents($configPath, $firstResult['source']);

        // Sync again — should find nothing new
        $secondResult = $this->syncer->sync($configPath);

        $this->assertEmpty($secondResult['added']);
    }

    public function test_skips_scrolls_with_unknown_extensions(): void
    {
        $configPath = $this->createConfigFile([
            'custom' => [
                'extensions' => ['yaml', 'json'],
                'prophets' => [],
            ],
        ]);

        $result = $this->syncer->sync($configPath);

        $this->assertEmpty($result['added']);
    }

    public function test_handles_empty_scrolls(): void
    {
        $configPath = $this->createConfigFile([]);

        $result = $this->syncer->sync($configPath);

        $this->assertEmpty($result['added']);
    }

    public function test_preserves_existing_config_structure(): void
    {
        $configPath = $this->createConfigFile([
            'backend' => [
                'extensions' => ['php'],
                'prophets' => [],
            ],
        ]);

        $originalSource = file_get_contents($configPath);
        $result = $this->syncer->sync($configPath);

        // Source should still be valid PHP
        $this->assertStringContainsString("'scrolls'", $result['source']);
        $this->assertStringContainsString("'backend'", $result['source']);
        $this->assertStringContainsString("'prophets'", $result['source']);
    }

    public function test_inserts_into_non_empty_prophets_array(): void
    {
        // Use a real prophet class that exists
        $existingProphet = 'JesseGall\\CodeCommandments\\Prophets\\Backend\\LongMethodProphet';

        $source = <<<'PHP'
<?php

return [
    'scrolls' => [
        'backend' => [
            'path' => __DIR__ . '/app',
            'extensions' => ['php'],
            'exclude' => [],
            'prophets' => [
                \JesseGall\CodeCommandments\Prophets\Backend\LongMethodProphet::class,
            ],
        ],
    ],
];
PHP;

        $configPath = $this->tempDir . '/commandments.php';
        file_put_contents($configPath, $source);

        $result = $this->syncer->sync($configPath);

        // LongMethodProphet should NOT be in the added list
        $addedClasses = array_column($result['added'], 'class');
        $this->assertNotContains($existingProphet, $addedClasses);

        // But other backend prophets should be added
        $this->assertNotEmpty($result['added']);

        // The source should contain both old and new prophets
        $this->assertStringContainsString('LongMethodProphet', $result['source']);
    }

    public function test_handles_prophets_with_config_options(): void
    {
        $configPath = $this->createConfigFile([
            'backend' => [
                'extensions' => ['php'],
                'prophets' => [],
            ],
        ]);

        $result = $this->syncer->sync($configPath);

        // The source should contain commented-out config options for prophets that have them
        // At least LongMethodProphet has max_method_lines
        $this->assertStringContainsString('LongMethodProphet', $result['source']);
    }

    public function test_syncs_both_backend_and_frontend_scrolls(): void
    {
        $configPath = $this->createConfigFile([
            'backend' => [
                'extensions' => ['php'],
                'prophets' => [],
            ],
            'frontend' => [
                'extensions' => ['vue', 'ts', 'js'],
                'prophets' => [],
            ],
        ]);

        $result = $this->syncer->sync($configPath);

        $scrolls = array_unique(array_column($result['added'], 'scroll'));
        $this->assertContains('backend', $scrolls);
        $this->assertContains('frontend', $scrolls);
    }

    public function test_after_filter_only_adds_prophets_introduced_later(): void
    {
        $configPath = $this->createConfigFile([
            'backend' => [
                'extensions' => ['php'],
                'prophets' => [],
            ],
        ]);

        // Filter at 1.3.5: LongMethodProphet (1.3.1) stays OUT,
        // NoArrayStringIndexingProphet (1.4.0) comes IN.
        $result = $this->syncer->sync($configPath, '1.3.5');

        $classes = array_column($result['added'], 'class');

        $this->assertContains(
            'JesseGall\\CodeCommandments\\Prophets\\Backend\\NoArrayStringIndexingProphet',
            $classes,
            'Expected NoArrayStringIndexingProphet (1.4.0) to be added'
        );
        $this->assertNotContains(
            'JesseGall\\CodeCommandments\\Prophets\\Backend\\LongMethodProphet',
            $classes,
            'Did not expect LongMethodProphet (1.3.1) to be re-added'
        );
    }

    public function test_after_filter_skips_untagged_prophets(): void
    {
        $configPath = $this->createConfigFile([
            'backend' => [
                'extensions' => ['php'],
                'prophets' => [],
            ],
        ]);

        $result = $this->syncer->sync($configPath, '0.0.0');

        $classes = array_column($result['added'], 'class');

        // Untagged legacy prophets should NOT be re-added via --after=0.0.0
        $this->assertNotContains(
            'JesseGall\\CodeCommandments\\Prophets\\Backend\\NoRawRequestProphet',
            $classes,
            'Untagged prophets should be skipped in filtered mode'
        );
        // Tagged ones (after 0.0.0) should come in
        $this->assertContains(
            'JesseGall\\CodeCommandments\\Prophets\\Backend\\NoArrayStringIndexingProphet',
            $classes,
        );
    }

    public function test_after_filter_excludes_equal_version(): void
    {
        $configPath = $this->createConfigFile([
            'backend' => [
                'extensions' => ['php'],
                'prophets' => [],
            ],
        ]);

        // --after=1.4.0 should NOT include NoArrayStringIndexing (introduced in exactly 1.4.0)
        // because the comparison is strictly greater-than.
        $result = $this->syncer->sync($configPath, '1.4.0');

        $classes = array_column($result['added'], 'class');
        $this->assertNotContains(
            'JesseGall\\CodeCommandments\\Prophets\\Backend\\NoArrayStringIndexingProphet',
            $classes,
        );
    }

    public function test_added_entries_include_introduced_in_version(): void
    {
        $configPath = $this->createConfigFile([
            'backend' => [
                'extensions' => ['php'],
                'prophets' => [],
            ],
        ]);

        $result = $this->syncer->sync($configPath);

        foreach ($result['added'] as $entry) {
            $this->assertArrayHasKey('introduced_in', $entry);
        }

        $indexed = array_column($result['added'], 'introduced_in', 'class');
        $this->assertSame(
            '1.4.0',
            $indexed['JesseGall\\CodeCommandments\\Prophets\\Backend\\NoArrayStringIndexingProphet'] ?? null,
        );
    }

    /**
     * Create a temporary config file from scroll definitions.
     */
    private function createConfigFile(array $scrolls): string
    {
        $lines = [];
        $lines[] = '<?php';
        $lines[] = '';
        $lines[] = 'return [';
        $lines[] = "    'scrolls' => [";

        foreach ($scrolls as $name => $config) {
            $extensions = implode("', '", $config['extensions'] ?? []);
            $lines[] = "        '{$name}' => [";
            $lines[] = "            'path' => __DIR__,";
            $lines[] = "            'extensions' => ['{$extensions}'],";
            $lines[] = "            'exclude' => [],";
            $lines[] = "            'prophets' => [],";
            $lines[] = '        ],';
        }

        $lines[] = '    ],';
        $lines[] = '];';
        $lines[] = '';

        $configPath = $this->tempDir . '/commandments.php';
        file_put_contents($configPath, implode("\n", $lines));

        return $configPath;
    }

    private function cleanTempDir(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        $files = scandir($dir);

        if ($files === false) {
            return;
        }

        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            $path = $dir . '/' . $file;

            if (is_dir($path)) {
                $this->cleanTempDir($path);
            } else {
                unlink($path);
            }
        }

        rmdir($dir);
    }
}
