<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Cli\Orchestration;

use JesseGall\CodeCommandments\Cli\Scope\GitFiles;
use JesseGall\CodeCommandments\Workspace;
use PHPUnit\Framework\TestCase;

/**
 * A worktree is its own git toplevel, so anything reading "the project" from where the process happens to
 * be gets a different answer inside a lane — and a `cd` persists across calls. For the board that is not
 * cosmetic: a worker on the wrong board cannot be refused a claim the root already holds, so the one
 * refusal that prevents two lanes building the same thing is bypassed by stepping into a directory.
 */
final class BoardAnchorTest extends TestCase
{
    private string $root;

    private string $lane;

    private string|false $priorProjectDir;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/cc-anchor-' . uniqid('', true);
        $this->lane = $this->root . '/.lanes/styling';
        mkdir($this->root, 0777, true);

        exec('cd ' . escapeshellarg($this->root) . ' && git init -q && git commit -q --allow-empty -m first 2>/dev/null');
        exec('cd ' . escapeshellarg($this->root) . ' && git worktree add -q -b styling ' . escapeshellarg($this->lane) . ' 2>/dev/null');

        // A plain shell has no harness variable, which is the case that was broken.
        $this->priorProjectDir = getenv('CLAUDE_PROJECT_DIR');
        putenv('CLAUDE_PROJECT_DIR');
    }

    protected function tearDown(): void
    {
        putenv($this->priorProjectDir === false ? 'CLAUDE_PROJECT_DIR' : 'CLAUDE_PROJECT_DIR=' . $this->priorProjectDir);
        exec('rm -rf ' . escapeshellarg($this->root));
    }

    /**
     * The whole point: one project, one board, whichever worktree you are standing in.
     */
    public function test_a_lane_and_the_root_resolve_to_the_same_session_folder(): void
    {
        $fromRoot = Workspace::ofSession($this->root, 'sess-1')->sessionDir();
        $fromLane = Workspace::ofSession($this->lane, 'sess-1')->sessionDir();

        $this->assertSame($fromRoot, $fromLane, 'a cd into a lane must not change which board is read');
        $this->assertStringNotContainsString('.lanes', $fromLane, 'the board lives with the project, not the worktree');
    }

    public function test_git_names_the_main_worktree_from_inside_a_lane(): void
    {
        $this->assertSame(
            realpath($this->root),
            realpath((string) new GitFiles()->projectRoot($this->lane)),
        );
    }

    /**
     * The harness saying which project this is still wins — it is a statement, where the git answer is an
     * inference from where the process stands.
     */
    public function test_the_harness_still_wins_where_it_speaks(): void
    {
        putenv('CLAUDE_PROJECT_DIR=' . $this->root);

        $this->assertStringContainsString(
            basename($this->root),
            Workspace::ofSession('/somewhere/else', 'sess-1')->sessionDir(),
        );
    }

    /**
     * Outside a repository there is nothing to ask, and the caller's own answer stands.
     */
    public function test_outside_a_repository_the_fallback_answers(): void
    {
        $loose = sys_get_temp_dir() . '/cc-loose-' . uniqid('', true);
        mkdir($loose, 0777, true);

        $this->assertStringContainsString(basename($loose), Workspace::ofSession($loose, 'sess-1')->sessionDir());

        exec('rm -rf ' . escapeshellarg($loose));
    }
}
