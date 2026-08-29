<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Cli\Journal;

use JesseGall\CodeCommandments\Cli\Journal\Journal;
use JesseGall\CodeCommandments\Cli\Journal\Kind;
use JesseGall\CodeCommandments\Hooks\Handlers\JournalRecorder;
use JesseGall\CodeCommandments\Hooks\RecordingHookIO;
use JesseGall\CodeCommandments\Tests\Cli\FakeGit;
use JesseGall\CodeCommandments\Workspace;
use PHPUnit\Framework\TestCase;

/**
 * A conversation is ONE thing wherever a command was run from, so its journal must be too. A hook resolves
 * its root from the git toplevel of wherever the shell happens to be — right for a plan, which belongs to
 * the worktree it is worked in, and wrong for a journal: a session that steps into a worktree would file
 * half its record there and read an empty one back at home.
 */
final class SessionAnchorTest extends TestCase
{
    private string $project;

    private string $worktree;

    private string|false $priorProjectDir;

    protected function setUp(): void
    {
        $home = sys_get_temp_dir() . '/cc-anchor-' . uniqid('', true);
        $this->project = $home . '/project';
        $this->worktree = $home . '/project/.lanes/styling';
        mkdir($this->worktree . '/.commandments', 0777, true);

        $this->priorProjectDir = getenv('CLAUDE_PROJECT_DIR');
        putenv('CLAUDE_PROJECT_DIR=' . $this->project);
    }

    protected function tearDown(): void
    {
        putenv($this->priorProjectDir === false ? 'CLAUDE_PROJECT_DIR' : 'CLAUDE_PROJECT_DIR=' . $this->priorProjectDir);
        exec('rm -rf ' . escapeshellarg(dirname($this->project)));
    }

    /**
     * The hook fires with the WORKTREE as its root, exactly as it does when the agent's shell has stepped
     * into a lane — and the entry must still land in the session's own journal.
     */
    public function test_a_message_written_from_a_worktree_is_filed_with_the_session(): void
    {
        $io = new RecordingHookIO([
            'hook_event_name' => 'MessageDisplay',
            'session_id' => 'sess-anchored',
            'turn_id' => 't',
            'message_id' => 'm',
            'index' => 0,
            'final' => true,
            'delta' => '[!discovery] filed from inside a lane',
        ], new FakeGit($this->worktree));

        new JournalRecorder($io)->run([]);

        $atHome = Journal::inSession(Workspace::at($this->project, 'sess-anchored'))->entries();
        $inLane = Journal::inSession(Workspace::at($this->worktree, 'sess-anchored'))->entries();

        $this->assertCount(1, $atHome, 'the session keeps one journal, at the project');
        $this->assertSame('[!discovery] filed from inside a lane', $atHome[0]->text);
        $this->assertSame([], $inLane, 'nothing is stranded in the worktree');
    }

    /**
     * With no harness saying which project this is — a person in their own terminal — the directory is all
     * there is to go on, and it is used.
     */
    public function test_without_a_project_the_directory_answers(): void
    {
        putenv('CLAUDE_PROJECT_DIR');

        $this->assertSame(
            Workspace::at($this->worktree, 'sess-anchored')->sessionDir(),
            Workspace::ofSession($this->worktree, 'sess-anchored')->sessionDir(),
        );
    }

    public function test_the_kind_is_still_recorded(): void
    {
        $io = new RecordingHookIO([
            'hook_event_name' => 'UserPromptSubmit',
            'session_id' => 'sess-anchored',
            'prompt' => 'motion.ts is FORBIDDEN',
        ], new FakeGit($this->worktree));

        new JournalRecorder($io)->run([]);

        $entries = Journal::inSession(Workspace::at($this->project, 'sess-anchored'))->entries();

        $this->assertSame(Kind::User, $entries[0]->kind);
    }
}
