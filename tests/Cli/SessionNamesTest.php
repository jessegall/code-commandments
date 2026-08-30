<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Cli;

use JesseGall\CodeCommandments\Cli\Console;
use JesseGall\CodeCommandments\Cli\Input;
use JesseGall\CodeCommandments\Cli\Journal\Entry;
use JesseGall\CodeCommandments\Cli\Journal\Journal;
use JesseGall\CodeCommandments\Cli\Journal\Kind;
use JesseGall\CodeCommandments\Cli\SessionCommand;
use JesseGall\CodeCommandments\Cli\State\SessionNames;
use JesseGall\CodeCommandments\Workspace;
use JesseGall\PhpTypes\Option;
use PHPUnit\Framework\TestCase;

/**
 * A session folder is a five-character hash, which is unreadable to somebody picking one out of a dozen
 * — and a session now holds its own plan, so it is the thing you come BACK to. Naming makes it findable
 * by a word, while every lookup still starts from the id the harness reports, since that is the only
 * thing an agent knows about itself.
 */
final class SessionNamesTest extends TestCase
{
    private string $root;

    private string|false $priorProjectDir;

    private string|false $priorSession;

    protected function setUp(): void
    {
        // Resolved, because a worktree's `.git` file names its repository by an absolute path and the
        // walk that reads it resolves too — an unresolved `/var` against a resolved `/private/var` is a
        // fixture that passes for the wrong reason.
        $this->root = realpath(sys_get_temp_dir()) . '/cc-names-' . uniqid('', true);
        mkdir($this->root . '/.commandments/sessions', 0777, true);
        $this->priorProjectDir = getenv('CLAUDE_PROJECT_DIR');
        $this->priorSession = getenv('CLAUDE_CODE_SESSION_ID');
    }

    protected function tearDown(): void
    {
        putenv($this->priorProjectDir === false ? 'CLAUDE_PROJECT_DIR' : 'CLAUDE_PROJECT_DIR=' . $this->priorProjectDir);
        putenv($this->priorSession === false ? 'CLAUDE_CODE_SESSION_ID' : 'CLAUDE_CODE_SESSION_ID=' . $this->priorSession);
        exec('rm -rf ' . escapeshellarg($this->root));
    }

    private function names(): SessionNames
    {
        return SessionNames::in($this->root . '/.commandments');
    }

    public function test_a_session_with_no_name_has_none(): void
    {
        $this->assertTrue($this->names()->nameOf('abc-123')->isNone());
        $this->assertTrue($this->names()->idOf('dissolution')->isNone());
    }

    public function test_a_named_session_is_found_from_either_end(): void
    {
        $this->assertTrue($this->names()->name('abc-123', 'dissolution'));

        $this->assertSame('dissolution', $this->names()->nameOf('abc-123')->unwrap());
        $this->assertSame('abc-123', $this->names()->idOf('dissolution')->unwrap());
    }

    /**
     * A session has ONE name. Renaming replaces rather than accumulating, or the folder it points at
     * would be ambiguous — the same rule a role's binding follows.
     */
    public function test_renaming_replaces_the_old_name(): void
    {
        $names = $this->names();
        $names->name('abc-123', 'dissolution');
        $names->name('abc-123', 'the-port');

        $this->assertSame('the-port', $names->nameOf('abc-123')->unwrap());
        $this->assertTrue($names->idOf('dissolution')->isNone(), 'the old name is gone, not kept beside it');
        $this->assertCount(1, $names->all());
    }

    /**
     * A name belongs to one session. Taking it for a second would make the folder ambiguous, so the
     * caller is told rather than the older mapping being quietly dropped.
     */
    public function test_a_name_already_taken_by_another_session_is_refused(): void
    {
        $names = $this->names();
        $names->name('abc-123', 'dissolution');

        $this->assertFalse($names->name('def-456', 'dissolution'));
        $this->assertSame('abc-123', $names->idOf('dissolution')->unwrap(), 'the first session keeps it');
    }

    public function test_naming_a_session_its_own_name_again_is_allowed(): void
    {
        $names = $this->names();
        $names->name('abc-123', 'dissolution');

        $this->assertTrue($names->name('abc-123', 'dissolution'));
    }

    public function test_forgetting_a_name_returns_the_session_to_its_hash(): void
    {
        $names = $this->names();
        $names->name('abc-123', 'dissolution');

        $this->assertTrue($names->forget('dissolution'));
        $this->assertTrue($names->nameOf('abc-123')->isNone());
        $this->assertFalse($names->forget('dissolution'), 'forgetting a name nothing holds says so');
    }

    /**
     * The point of the map: the folder follows the name, so a person reads `sessions/dissolution/`
     * rather than `sessions/c3f8f/` — and the agent still finds it from the id it was handed.
     */
    public function test_the_workspace_folder_follows_the_name(): void
    {
        $unnamed = Workspace::at($this->root, 'abc-123');

        $this->assertSame(Workspace::keyFor('abc-123'), $unnamed->sessionKey());

        $this->names()->name('abc-123', 'dissolution');

        $this->assertSame('dissolution', Workspace::at($this->root, 'abc-123')->sessionKey());
        $this->assertStringEndsWith('/sessions/dissolution', Workspace::at($this->root, 'abc-123')->sessionDir());
    }

    /**
     * A session the map says nothing about is unaffected — naming one must not move another.
     */
    public function test_an_unnamed_session_keeps_its_hash(): void
    {
        $this->names()->name('abc-123', 'dissolution');

        $this->assertSame(Workspace::keyFor('def-456'), Workspace::at($this->root, 'def-456')->sessionKey());
    }

    /**
     * @param  list<string>  $worktrees
     */
    private function session(array $worktrees, string ...$args): array
    {
        $out = fopen('php://memory', 'r+');

        $code = new SessionCommand(new CapturingHookIO(new FakeGit($this->root, worktrees: $worktrees)), new Console($out))
            ->run(Input::fromArgv(['commandments', 'session', ...$args]));

        rewind($out);

        return [$code, (string) stream_get_contents($out)];
    }

    /**
     * The mechanism is only reachable if a verb sets it. Naming renames the FOLDER too, so the name and
     * the directory can never disagree — and the session's state, its plan included, moves with it.
     */
    public function test_the_command_names_a_session_and_moves_its_folder(): void
    {
        putenv('CLAUDE_PROJECT_DIR=' . $this->root);
        putenv('CLAUDE_CODE_SESSION_ID=abc-123');

        $was = Workspace::at($this->root, 'abc-123')->sessionDir();
        mkdir($was . '/plan', 0777, true);
        file_put_contents($was . '/plan/README.md', 'the work');

        [$code, $said] = $this->session([], 'name', 'dissolution');

        $this->assertSame(0, $code);
        $this->assertStringContainsString('dissolution', $said);
        $this->assertDirectoryExists($this->root . '/.commandments/sessions/dissolution');
        $this->assertFileExists($this->root . '/.commandments/sessions/dissolution/plan/README.md', 'the plan came with it');
        $this->assertDirectoryDoesNotExist($was, 'and the hash folder is gone, not duplicated');
    }

    public function test_a_name_another_session_holds_is_refused(): void
    {
        putenv('CLAUDE_PROJECT_DIR=' . $this->root);
        putenv('CLAUDE_CODE_SESSION_ID=abc-123');
        $this->names()->name('def-456', 'dissolution');

        [$code, $said] = $this->session([], 'name', 'dissolution');

        $this->assertSame(Console::REFUSED, $code);
        $this->assertStringContainsString('already belongs', $said);
    }

    public function test_naming_with_no_name_is_refused(): void
    {
        putenv('CLAUDE_PROJECT_DIR=' . $this->root);
        putenv('CLAUDE_CODE_SESSION_ID=abc-123');

        $this->assertSame(Console::REFUSED, $this->session([], 'name')[0]);
    }

    public function test_forgetting_returns_the_folder_to_its_hash(): void
    {
        putenv('CLAUDE_PROJECT_DIR=' . $this->root);
        putenv('CLAUDE_CODE_SESSION_ID=abc-123');

        mkdir(Workspace::at($this->root, 'abc-123')->sessionDir(), 0777, true);
        $this->session([], 'name', 'dissolution');

        [$code] = $this->session([], 'forget', 'dissolution');

        $this->assertSame(0, $code);
        $this->assertDirectoryExists($this->root . '/.commandments/sessions/' . Workspace::keyFor('abc-123'));
        $this->assertDirectoryDoesNotExist($this->root . '/.commandments/sessions/dissolution');
    }

    public function test_forgetting_a_name_nothing_holds_is_refused(): void
    {
        putenv('CLAUDE_PROJECT_DIR=' . $this->root);

        $this->assertSame(Console::REFUSED, $this->session([], 'forget', 'nothing')[0]);
    }

    /**
     * A lane is a checkout of its own, and `sessions/.names` is generated state — so a worktree has no map
     * at all and resolved a NAMED session straight back to its hash. The same session then filed its
     * worktree-scoped state under `sessions/<hash>` while its journal went to `sessions/<name>`, and nothing
     * reconciled the two. The name belongs to the SESSION, so it is read from the repository.
     */
    public function test_a_named_session_keeps_its_name_inside_a_worktree(): void
    {
        $tree = $this->worktree();

        $this->names()->name('abc-123', 'dissolution');

        $this->assertSame('dissolution', Workspace::at($tree, 'abc-123')->sessionKey());
        $this->assertSame($tree . '/.commandments/sessions/dissolution', Workspace::at($tree, 'abc-123')->sessionDir());
    }

    /**
     * Naming moves the folder in EVERY checkout, or a lane that had already written its plan and counters
     * goes on holding them under the key the session no longer answers to.
     */
    public function test_naming_moves_the_folder_in_every_checkout(): void
    {
        putenv('CLAUDE_PROJECT_DIR=' . $this->root);
        putenv('CLAUDE_CODE_SESSION_ID=abc-123');

        $tree = $this->worktree();
        $lane = Workspace::at($tree, 'abc-123')->sessionDir();
        mkdir($lane, 0777, true);
        file_put_contents($lane . '/.plan-active', 'the work the lane holds');

        [$code] = $this->session([$tree], 'name', 'dissolution');

        $this->assertSame(0, $code);
        $this->assertFileExists($tree . '/.commandments/sessions/dissolution/.plan-active');
        $this->assertDirectoryDoesNotExist($lane, 'the folder in the lane followed the name too');
    }

    /**
     * A hash folder no transcript and no name accounts for reads exactly like a live session, so somebody
     * tidying up deletes a stretch of the record without knowing it was one. It is named as what it is.
     */
    public function test_list_marks_a_folder_nothing_points_at_as_an_orphan(): void
    {
        putenv('CLAUDE_PROJECT_DIR=' . $this->root);
        $this->names()->name('abc-123', 'dissolution');
        mkdir(Workspace::at($this->root, 'abc-123')->sessionDir(), 0777, true);
        mkdir(Workspace::at($this->root)->sessionDirNamed('9da82'), 0777, true);

        [$code, $said] = $this->session([], 'list');

        $this->assertSame(0, $code);
        $this->assertStringContainsString('9da82', $said);
        $this->assertStringContainsString('ORPHAN', $said);

        foreach (explode("\n", $said) as $line) {
            if (str_contains($line, 'dissolution')) {
                $this->assertStringNotContainsString('ORPHAN', $line, 'a named folder is not an orphan');
            }
        }
    }

    /**
     * Adopting takes what the folder HOLDS — a `reports/` directory no list named — and merges the one file
     * both sides have, interleaved, so the earlier stretch reads before the later one.
     */
    public function test_adopt_takes_a_stranded_folder_into_this_session(): void
    {
        putenv('CLAUDE_PROJECT_DIR=' . $this->root);
        putenv('CLAUDE_CODE_SESSION_ID=abc-123');
        $this->names()->name('abc-123', 'dissolution');

        $mine = Workspace::at($this->root, 'abc-123')->sessionDir();
        Journal::at($mine . '/.journal')->file($this->entry('2026-08-30T05:00:00Z', 'the later stretch'));

        $stranded = Workspace::at($this->root)->sessionDirNamed('9da82');
        Journal::at($stranded . '/.journal')->file($this->entry('2026-08-30T03:00:00Z', 'the earlier stretch'));
        mkdir($stranded . '/reports', 0777, true);
        file_put_contents($stranded . '/reports/one.md', 'a report');
        file_put_contents($stranded . '/.cardinal-remind-count', 'count: 3');

        [$code, $said] = $this->session([], 'adopt', '9da82');

        $this->assertSame(0, $code, $said);
        $this->assertSame(
            ['the earlier stretch', 'the later stretch'],
            array_map(fn (Entry $entry) => $entry->text, Journal::at($mine . '/.journal')->entries()),
        );
        $this->assertFileExists($mine . '/reports/one.md', 'a folder no list named came across too');
        $this->assertFileExists($mine . '/.cardinal-remind-count');
        $this->assertDirectoryDoesNotExist($stranded, 'nothing was left behind, so no stub survives it');
    }

    /**
     * What nothing knows how to merge is KEPT, and the folder stays standing — deleting a record that did
     * not come across is the loss the whole verb exists to prevent, so the answer is a refusal.
     */
    public function test_adopt_keeps_what_it_cannot_merge_and_refuses(): void
    {
        putenv('CLAUDE_PROJECT_DIR=' . $this->root);
        putenv('CLAUDE_CODE_SESSION_ID=abc-123');

        $mine = Workspace::at($this->root, 'abc-123')->sessionDir();
        mkdir($mine, 0777, true);
        file_put_contents($mine . '/.touched-sources', 'marked-at: 2');

        $stranded = Workspace::at($this->root)->sessionDirNamed('9da82');
        mkdir($stranded, 0777, true);
        file_put_contents($stranded . '/.touched-sources', 'marked-at: 1');
        file_put_contents($stranded . '/.judge-reminded', 'count: 1');

        [$code, $said] = $this->session([], 'adopt', '9da82');

        $this->assertSame(Console::REFUSED, $code);
        $this->assertStringContainsString('KEPT', $said);
        $this->assertFileExists($mine . '/.judge-reminded', 'what could move, moved');
        $this->assertStringEqualsFile($stranded . '/.touched-sources', 'marked-at: 1', 'and what could not was not deleted');
        $this->assertStringEqualsFile($mine . '/.touched-sources', 'marked-at: 2', 'nor did it overwrite what was there');
    }

    public function test_adopting_a_folder_nothing_holds_is_refused(): void
    {
        putenv('CLAUDE_PROJECT_DIR=' . $this->root);
        putenv('CLAUDE_CODE_SESSION_ID=abc-123');

        $this->assertSame(Console::REFUSED, $this->session([], 'adopt', 'nowhere')[0]);
        $this->assertSame(Console::REFUSED, $this->session([], 'adopt')[0], 'and so is naming none at all');
    }

    /**
     * A checkout of this repository, as git writes one: its `.git` is a FILE naming the main worktree's
     * `worktrees/` entry, which is how anything standing in the lane finds the repository it belongs to.
     */
    private function worktree(): string
    {
        mkdir($this->root . '/.git', 0777, true);

        $tree = $this->root . '/tree';
        mkdir($tree . '/.commandments/sessions', 0777, true);
        file_put_contents($tree . '/.git', 'gitdir: ' . $this->root . '/.git/worktrees/tree' . "\n");

        return $tree;
    }

    private function entry(string $moment, string $text): Entry
    {
        return new Entry(Kind::Agent, $moment, 'turn-1', uniqid('msg-', true), Option::none(), $text);
    }

}
