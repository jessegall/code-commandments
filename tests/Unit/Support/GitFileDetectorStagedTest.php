<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Support;

use JesseGall\CodeCommandments\Support\GitFileDetector;
use JesseGall\CodeCommandments\Tests\TestCase;

class GitFileDetectorStagedTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir() . '/cc-staged-' . uniqid();
        mkdir($this->dir);
        $this->git('init -q');
        $this->git('config user.email t@t.t');
        $this->git('config user.name t');
    }

    protected function tearDown(): void
    {
        shell_exec('rm -rf ' . escapeshellarg($this->dir));
        parent::tearDown();
    }

    private function git(string $args): void
    {
        shell_exec('git -C ' . escapeshellarg($this->dir) . ' ' . $args . ' 2>/dev/null');
    }

    public function test_returns_only_staged_files(): void
    {
        file_put_contents($this->dir . '/staged.php', "<?php\n");
        file_put_contents($this->dir . '/unstaged.php', "<?php\n");
        $this->git('add staged.php');

        $staged = GitFileDetector::for($this->dir)->getStagedFiles();

        $this->assertCount(1, $staged);
        $this->assertStringEndsWith('/staged.php', $staged[0]);
    }

    public function test_empty_when_nothing_staged(): void
    {
        file_put_contents($this->dir . '/dirty.php', "<?php\n");

        $this->assertSame([], GitFileDetector::for($this->dir)->getStagedFiles());
    }
}
