<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Support;

use JesseGall\CodeCommandments\Support\GitignoreInstaller;
use JesseGall\CodeCommandments\Tests\TestCase;

class GitignoreInstallerTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir() . '/cc-gitignore-' . uniqid();
        mkdir($this->dir, 0755, true);
    }

    protected function tearDown(): void
    {
        @unlink($this->dir . '/.gitignore');
        @rmdir($this->dir);
        parent::tearDown();
    }

    public function test_creates_a_fresh_gitignore(): void
    {
        $status = (new GitignoreInstaller())->ensure($this->dir);

        $this->assertSame(GitignoreInstaller::STATUS_INSTALLED, $status);

        $content = file_get_contents($this->dir . '/.gitignore');
        $this->assertStringContainsString('.commandments/', $content);
        $this->assertStringContainsString('.commandments-reports.json', $content);
        $this->assertStringContainsString('.commandments-last-synced', $content);
        $this->assertStringContainsString('code-commandments generated state', $content);
    }

    public function test_appends_to_an_existing_gitignore_without_clobbering(): void
    {
        file_put_contents($this->dir . '/.gitignore', "/vendor/\n.env\n");

        $status = (new GitignoreInstaller())->ensure($this->dir);

        $this->assertSame(GitignoreInstaller::STATUS_APPENDED, $status);

        $content = file_get_contents($this->dir . '/.gitignore');
        $this->assertStringContainsString('/vendor/', $content);
        $this->assertStringContainsString('.env', $content);
        $this->assertStringContainsString('.commandments/', $content);
    }

    public function test_is_idempotent_when_block_is_current(): void
    {
        $installer = new GitignoreInstaller();
        $installer->ensure($this->dir);

        $this->assertSame(GitignoreInstaller::STATUS_ALREADY_PRESENT, $installer->ensure($this->dir));

        // The block is written exactly once — no duplicate appends.
        $content = file_get_contents($this->dir . '/.gitignore');
        $this->assertSame(1, substr_count($content, '# >>> code-commandments generated state >>>'));
    }

    public function test_refreshes_a_stale_block_in_place(): void
    {
        $stale = "/vendor/\n\n# >>> code-commandments generated state >>>\n.commandments/\n# <<< code-commandments generated state <<<\n";
        file_put_contents($this->dir . '/.gitignore', $stale);

        $status = (new GitignoreInstaller())->ensure($this->dir);

        $this->assertSame(GitignoreInstaller::STATUS_UPDATED, $status);

        $content = file_get_contents($this->dir . '/.gitignore');
        $this->assertStringContainsString('/vendor/', $content);
        $this->assertStringContainsString('.commandments-reports.json', $content);
        // Still exactly one managed block after the refresh.
        $this->assertSame(1, substr_count($content, '# >>> code-commandments generated state >>>'));
    }
}
