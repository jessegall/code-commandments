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
     * The case that shipped broken: the harness stamps `CLAUDE_PROJECT_DIR` PER AGENT, so a worker
     * running in a lane carries the LANE as its project directory. Taking that at its word put the
     * conversation's own record inside the worktree — two boards for one build, each consistent with
     * itself and neither able to see the other, which is worse than an empty one because it does not
     * announce itself. Git wins here precisely because it answers about the repository rather than
     * about the agent.
     */
    public function test_a_lane_agents_own_project_dir_does_not_move_the_board(): void
    {
        putenv('CLAUDE_PROJECT_DIR=' . $this->lane);

        $resolved = Workspace::ofSession($this->lane, 'sess-1')->sessionDir();

        $this->assertStringNotContainsString('.lanes', $resolved, 'a lane agent still writes to the project board');
        $this->assertSame(Workspace::ofSession($this->root, 'sess-1')->sessionDir(), $resolved);
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
