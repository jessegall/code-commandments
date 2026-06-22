<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Support;

use JesseGall\CodeCommandments\Support\ClaudeMdInstaller;
use PHPUnit\Framework\TestCase;

class ClaudeMdInstallerRemoveTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir() . '/cc-md-remove-' . uniqid();
        mkdir($this->dir, 0755, true);
    }

    protected function tearDown(): void
    {
        shell_exec('rm -rf ' . escapeshellarg($this->dir));
        parent::tearDown();
    }

    private function write(string $content): void
    {
        file_put_contents($this->dir . '/CLAUDE.md', $content);
    }

    private function read(): string
    {
        return (string) file_get_contents($this->dir . '/CLAUDE.md');
    }

    public function test_removes_sentinel_section_and_preserves_surrounding_content(): void
    {
        $this->write("# My Project\n\nIntro.\n\n" . ClaudeMdInstaller::BEGIN . "\n## Code Commandments\nstuff\n" . ClaudeMdInstaller::END . "\n\n## Keep\nmine\n");

        $status = ClaudeMdInstaller::remove($this->dir);

        $this->assertSame(ClaudeMdInstaller::STATUS_REMOVED, $status);
        $out = $this->read();
        $this->assertStringNotContainsString(ClaudeMdInstaller::BEGIN, $out);
        $this->assertStringNotContainsString('## Code Commandments', $out);
        $this->assertStringContainsString('# My Project', $out);
        $this->assertStringContainsString('## Keep', $out);
        $this->assertStringContainsString('mine', $out);
        // No giant blank gap left behind.
        $this->assertStringNotContainsString("\n\n\n\n", $out);
    }

    public function test_removes_legacy_heading_section(): void
    {
        $this->write("# Title\n\n## Code Commandments\n\nold body\n\n## Other\nkeep\n");

        $status = ClaudeMdInstaller::remove($this->dir);

        $this->assertSame(ClaudeMdInstaller::STATUS_REMOVED, $status);
        $out = $this->read();
        $this->assertStringNotContainsString('## Code Commandments', $out);
        $this->assertStringNotContainsString('old body', $out);
        $this->assertStringContainsString('## Other', $out);
    }

    public function test_no_section_is_a_no_op(): void
    {
        $this->write("# Title\n\nNothing to remove.\n");

        $this->assertSame(ClaudeMdInstaller::STATUS_NO_SECTION, ClaudeMdInstaller::remove($this->dir));
    }

    public function test_missing_file_is_a_no_op(): void
    {
        $this->assertSame(ClaudeMdInstaller::STATUS_NO_SECTION, ClaudeMdInstaller::remove($this->dir));
    }

    public function test_skips_a_file_with_merge_conflict_markers(): void
    {
        $this->write("<<<<<<< HEAD\n" . ClaudeMdInstaller::BEGIN . "\nx\n" . ClaudeMdInstaller::END . "\n=======\n>>>>>>> other\n");

        $this->assertSame(ClaudeMdInstaller::STATUS_SKIPPED_CONFLICT, ClaudeMdInstaller::remove($this->dir));
    }
}
