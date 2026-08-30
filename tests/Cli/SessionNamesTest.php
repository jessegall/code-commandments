<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Cli;

use JesseGall\CodeCommandments\Cli\Console;
use JesseGall\CodeCommandments\Cli\Input;
use JesseGall\CodeCommandments\Cli\SessionCommand;
use JesseGall\CodeCommandments\Cli\State\SessionNames;
use JesseGall\CodeCommandments\Workspace;
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
        $this->root = sys_get_temp_dir() . '/cc-names-' . uniqid('', true);
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

    private function session(string ...$args): array
    {
        $out = fopen('php://memory', 'r+');

        $code = new SessionCommand(new CapturingHookIO(new FakeGit($this->root)), new Console($out))
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

        [$code, $said] = $this->session('name', 'dissolution');

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

        [$code, $said] = $this->session('name', 'dissolution');

        $this->assertSame(Console::REFUSED, $code);
        $this->assertStringContainsString('already belongs', $said);
    }

    public function test_naming_with_no_name_is_refused(): void
    {
        putenv('CLAUDE_PROJECT_DIR=' . $this->root);
        putenv('CLAUDE_CODE_SESSION_ID=abc-123');

        $this->assertSame(Console::REFUSED, $this->session('name')[0]);
    }

    public function test_forgetting_returns_the_folder_to_its_hash(): void
    {
        putenv('CLAUDE_PROJECT_DIR=' . $this->root);
        putenv('CLAUDE_CODE_SESSION_ID=abc-123');

        mkdir(Workspace::at($this->root, 'abc-123')->sessionDir(), 0777, true);
        $this->session('name', 'dissolution');

        [$code] = $this->session('forget', 'dissolution');

        $this->assertSame(0, $code);
        $this->assertDirectoryExists($this->root . '/.commandments/sessions/' . Workspace::keyFor('abc-123'));
        $this->assertDirectoryDoesNotExist($this->root . '/.commandments/sessions/dissolution');
    }

    public function test_forgetting_a_name_nothing_holds_is_refused(): void
    {
        putenv('CLAUDE_PROJECT_DIR=' . $this->root);

        $this->assertSame(Console::REFUSED, $this->session('forget', 'nothing')[0]);
    }
}
