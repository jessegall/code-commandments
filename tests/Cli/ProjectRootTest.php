<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Cli;

use JesseGall\CodeCommandments\Hooks\HookIO;
use PHPUnit\Framework\TestCase;

final class ProjectRootTest extends TestCase
{
    private string $dir;

    private string $cwd;

    protected function setUp(): void
    {
        $this->cwd = getcwd() ?: '.';
        $this->dir = realpath(sys_get_temp_dir()) . '/cc-root-' . uniqid('', true);

        mkdir($this->dir . '/project/src', 0777, true);
        mkdir($this->dir . '/project/.git', 0777, true);
        mkdir($this->dir . '/other/src', 0777, true);
        mkdir($this->dir . '/other/.git', 0777, true);
    }

    protected function tearDown(): void
    {
        chdir($this->cwd);
        putenv('CLAUDE_PROJECT_DIR');
        exec('rm -rf ' . escapeshellarg($this->dir));
    }

    public function test_a_directory_inside_the_project_resolves_to_the_project(): void
    {
        putenv("CLAUDE_PROJECT_DIR={$this->dir}/project");
        chdir($this->dir . '/project/src');

        $this->assertSame($this->dir . '/project', new HookIO()->projectRoot());
    }

    public function test_another_repository_never_re_points_the_session(): void
    {
        putenv("CLAUDE_PROJECT_DIR={$this->dir}/project");
        chdir($this->dir . '/other/src');

        // The shell stepped into an unrelated checkout; the session is still the project's, so its
        // state must not be written under this session's key in a project that knows nothing about it.
        $this->assertSame($this->dir . '/project', new HookIO()->projectRoot());
    }

    public function test_a_worktree_of_the_project_is_scoped_to_itself(): void
    {
        mkdir($this->dir . '/tree', 0777, true);
        file_put_contents($this->dir . '/tree/.git', "gitdir: {$this->dir}/project/.git/worktrees/tree\n");

        putenv("CLAUDE_PROJECT_DIR={$this->dir}/project");
        chdir($this->dir . '/tree');

        $this->assertSame($this->dir . '/tree', new HookIO()->projectRoot());
    }

    public function test_without_a_declared_project_the_git_root_still_wins(): void
    {
        putenv('CLAUDE_PROJECT_DIR');
        chdir($this->dir . '/other/src');

        $this->assertSame($this->dir . '/other', new HookIO()->projectRoot());
    }
}
