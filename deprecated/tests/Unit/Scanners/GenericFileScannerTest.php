<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Scanners;

use JesseGall\CodeCommandments\Scanners\GenericFileScanner;
use JesseGall\CodeCommandments\Tests\TestCase;

class GenericFileScannerTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();

        $root = sys_get_temp_dir() . '/code-commandments-scanner-' . uniqid();
        mkdir($root . '/app/Console', 0777, true);
        mkdir($root . '/tests/Feature/Octane', 0777, true);
        mkdir($root . '/database/migrations', 0777, true);
        mkdir($root . '/src', 0777, true);

        file_put_contents($root . '/src/Service.php', "<?php\n");
        file_put_contents($root . '/app/Console/Kernel.php', "<?php\n");
        file_put_contents($root . '/tests/Feature/Octane/WorkerTest.php', "<?php\n");
        file_put_contents($root . '/database/migrations/2025_01_01_000000_create_users.php', "<?php\n");

        // Resolve symlinks (macOS /var → /private/var) so getRealPath()
        // results match the prefix we strip for relative-path comparisons.
        $this->root = realpath($root);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->root);
        parent::tearDown();
    }

    public function test_absolute_exclude_paths_prune_entire_directories(): void
    {
        // The bug: __DIR__-style absolute paths went straight to Finder::notPath()
        // which only matches RELATIVE paths from the scan root, so entries
        // like '/abs/proj/tests/' never matched.
        $files = $this->scan($this->root, [
            $this->root . '/tests/',
            $this->root . '/database/',
        ]);

        $this->assertContains('src/Service.php', $files);
        $this->assertContains('app/Console/Kernel.php', $files);
        $this->assertNotContains('tests/Feature/Octane/WorkerTest.php', $files);
        $this->assertNotContains('database/migrations/2025_01_01_000000_create_users.php', $files);
    }

    public function test_relative_directory_excludes_still_work(): void
    {
        $files = $this->scan($this->root, ['tests', 'database']);

        $this->assertContains('src/Service.php', $files);
        $this->assertNotContains('tests/Feature/Octane/WorkerTest.php', $files);
        $this->assertNotContains('database/migrations/2025_01_01_000000_create_users.php', $files);
    }

    public function test_file_pattern_excludes_still_work(): void
    {
        // The pre-existing semantics: a file path with an extension is matched
        // via Finder::notPath() rather than pruned at scan time.
        $files = $this->scan($this->root, ['Console/Kernel.php']);

        $this->assertContains('src/Service.php', $files);
        $this->assertNotContains('app/Console/Kernel.php', $files);
    }

    public function test_glob_excludes_still_work(): void
    {
        file_put_contents($this->root . '/src/types.d.ts', '');

        $files = $this->scan($this->root, ['*.d.ts']);

        $this->assertNotContains('src/types.d.ts', $files);
    }

    public function test_default_excludes_skip_vendor_and_node_modules(): void
    {
        mkdir($this->root . '/vendor/foo', 0777, true);
        mkdir($this->root . '/node_modules/bar', 0777, true);
        file_put_contents($this->root . '/vendor/foo/Class.php', "<?php\n");
        file_put_contents($this->root . '/node_modules/bar/index.js', '');

        $files = $this->scan($this->root, []);

        $this->assertNotContains('vendor/foo/Class.php', $files);
        $this->assertNotContains('node_modules/bar/index.js', $files);
    }

    public function test_excluding_the_scan_root_excludes_everything(): void
    {
        // Degenerate input but the behavior should be predictable: every
        // file lives under the excluded path, so nothing comes back.
        $files = $this->scan($this->root, [$this->root]);

        $this->assertSame([], $files);
    }

    /**
     * @param  array<string>  $excludePaths
     * @return array<string>
     */
    private function scan(string $path, array $excludePaths): array
    {
        $scanner = new GenericFileScanner();
        $files = [];

        foreach ($scanner->scan($path, ['php', 'js', 'ts'], $excludePaths) as $file) {
            $real = $file->getRealPath();

            if ($real === false) {
                continue;
            }

            $files[] = ltrim(str_replace($this->root, '', $real), '/');
        }

        return $files;
    }

    private function removeDir(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        foreach (scandir($dir) as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = $dir . '/' . $entry;
            is_dir($path) ? $this->removeDir($path) : unlink($path);
        }

        rmdir($dir);
    }
}
