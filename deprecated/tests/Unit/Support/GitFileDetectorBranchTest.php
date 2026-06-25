<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Support;

use JesseGall\CodeCommandments\Support\GitFileDetector;
use PHPUnit\Framework\TestCase;

class GitFileDetectorBranchTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir() . '/cc-branch-' . uniqid();
        mkdir($this->dir, 0755, true);
        $this->git('init -q -b main');
        $this->git('config user.email t@t');
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

    public function test_branch_scope_includes_committed_work_that_git_scope_misses(): void
    {
        // Base commit on main.
        file_put_contents($this->dir . '/base.php', "<?php\n");
        $this->git('add -A');
        $this->git('commit -qm base');

        // Feature branch: add a file AND COMMIT it (so it leaves the working tree).
        $this->git('checkout -q -b feature');
        file_put_contents($this->dir . '/feature.php', "<?php\n");
        $this->git('add -A');
        $this->git('commit -qm feature');

        $detector = GitFileDetector::for($this->dir);

        $branch = $detector->getBranchFiles();
        $changed = $detector->getChangedFiles();

        // Branch scope sees the committed feature file...
        $this->assertContains($this->dir . '/feature.php', $branch);
        // ...while the diff-vs-HEAD scope does not (it was committed).
        $this->assertNotContains($this->dir . '/feature.php', $changed);
    }

    public function test_branch_scope_also_includes_uncommitted_work(): void
    {
        file_put_contents($this->dir . '/base.php', "<?php\n");
        $this->git('add -A');
        $this->git('commit -qm base');

        $this->git('checkout -q -b feature');
        file_put_contents($this->dir . '/wip.php', "<?php\n"); // untracked, uncommitted

        $this->assertContains($this->dir . '/wip.php', GitFileDetector::for($this->dir)->getBranchFiles());
    }
}
