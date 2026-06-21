<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Feature;

use JesseGall\CodeCommandments\Console\SyncConsoleCommand;
use JesseGall\CodeCommandments\Tests\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * The standalone `sync` command must re-assert the .gitignore block (parity with
 * the Laravel artisan SyncCommand). Regression guard: consumers driven by the
 * standalone CLI (a package with no artisan, or an app whose boot crashes) were
 * never getting the managed block because only the artisan path called it.
 */
class SyncConsoleGitignoreTest extends TestCase
{
    private string $dir;

    private string $originalCwd;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalCwd = (string) getcwd();
        $this->dir = sys_get_temp_dir() . '/cc-sync-gitignore-' . uniqid();
        mkdir($this->dir, 0755, true);

        // Minimal, loadable config — scaffold/skills auto OFF so the test only
        // exercises the gitignore step (no stray app/Support or .claude writes).
        file_put_contents($this->dir . '/commandments.php', <<<'PHP'
<?php

return [
    'scaffold' => ['auto' => false],
    'skills' => ['auto' => false],
    'scrolls' => [],
];
PHP);
    }

    protected function tearDown(): void
    {
        chdir($this->originalCwd);
        shell_exec('rm -rf ' . escapeshellarg($this->dir));

        parent::tearDown();
    }

    private function runSync(): void
    {
        chdir($this->dir);

        $tester = new CommandTester(new SyncConsoleCommand());
        $tester->execute(['--config' => 'commandments.php']);
    }

    public function test_standalone_sync_writes_the_managed_gitignore_block(): void
    {
        $this->runSync();

        $gitignore = $this->dir . '/.gitignore';
        $this->assertFileExists($gitignore);

        $content = (string) file_get_contents($gitignore);
        $this->assertStringContainsString('# >>> code-commandments generated state >>>', $content);
        $this->assertStringContainsString('.commandments/', $content);
        $this->assertStringContainsString('.commandments-reports.json', $content);
        $this->assertStringContainsString('.commandments-last-synced', $content);
    }

    public function test_is_idempotent_no_duplicate_block(): void
    {
        $this->runSync();
        $this->runSync();

        $content = (string) file_get_contents($this->dir . '/.gitignore');
        $this->assertSame(
            1,
            substr_count($content, '# >>> code-commandments generated state >>>'),
            'the managed block must not be duplicated on a second sync',
        );
    }
}
