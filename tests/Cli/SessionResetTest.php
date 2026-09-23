<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Cli;

use JesseGall\CodeCommandments\Hooks\Counter;
use JesseGall\CodeCommandments\Hooks\Handlers\SessionReset;
use JesseGall\CodeCommandments\Tests\Concerns\TemporaryProject;
use JesseGall\CodeCommandments\Workspace;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The fresh-session cleanup: it wipes the session's reminder counters on a genuinely-new session
 * (`startup`/`clear`) — but leaves them intact when a session merely continues (`resume`/`compact`),
 * since compaction re-fires SessionStart mid-run.
 */
final class SessionResetTest extends TestCase
{
    use TemporaryProject;

    private function arm(): void
    {
        Counter::named(Workspace::at($this->root), 'cardinal-remind')->bump();
    }

    private function fire(string $source): void
    {
        $io = new CapturingHookIO(new FakeGit($this->root), ['hook_event_name' => 'SessionStart', 'source' => $source]);
        new SessionReset($io)->run([]);
    }

    public function test_a_fresh_startup_wipes_the_lingering_counters(): void
    {
        $this->arm();

        $this->fire('startup');

        $this->assertFileDoesNotExist(Workspace::at($this->root)->path('.cardinal-remind-count'), 'the reminder counter is wiped');
    }

    public function test_clear_also_wipes(): void
    {
        $this->arm();

        $this->fire('clear');

        $this->assertFileDoesNotExist(Workspace::at($this->root)->path('.cardinal-remind-count'));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function continuations(): array
    {
        return ['a compaction' => ['compact'], 'a resume' => ['resume']];
    }

    #[DataProvider('continuations')]
    public function test_a_start_that_continues_a_live_session_leaves_the_counters_intact(string $source): void
    {
        $this->arm();

        $this->fire($source);

        $this->assertFileExists(Workspace::at($this->root)->path('.cardinal-remind-count'));
    }

    public function test_the_wipe_is_scoped_to_the_payload_session_only(): void
    {
        $mine = Workspace::at($this->root, 'session-abc');
        $concurrent = Workspace::at($this->root, 'session-xyz');
        Counter::named($mine, 'cardinal-remind')->bump();
        Counter::named($concurrent, 'cardinal-remind')->bump();

        $io = new CapturingHookIO(new FakeGit($this->root), ['hook_event_name' => 'SessionStart', 'source' => 'startup', 'session_id' => 'session-abc']);
        new SessionReset($io)->run([]);

        $this->assertFileDoesNotExist($mine->path('.cardinal-remind-count'), "the firing session's counters are wiped");
        $this->assertFileExists($concurrent->path('.cardinal-remind-count'), "a concurrent session's counters are untouched");
    }

    public function test_a_fresh_startup_prunes_stale_session_folders(): void
    {
        $stale = Workspace::at($this->root, 'old-session');
        mkdir($stale->sessionDir(), 0777, true);
        touch($stale->sessionDir(), time() - 30 * 86400);

        $fresh = Workspace::at($this->root, 'fresh-session');
        mkdir($fresh->sessionDir(), 0777, true);

        $this->fire('startup');

        $this->assertDirectoryDoesNotExist($stale->sessionDir(), 'a long-abandoned session folder is swept');
        $this->assertDirectoryExists($fresh->sessionDir(), 'a recently-active sibling survives');
    }

    public function test_a_continuing_session_does_not_prune(): void
    {
        $stale = Workspace::at($this->root, 'old-session');
        mkdir($stale->sessionDir(), 0777, true);
        touch($stale->sessionDir(), time() - 30 * 86400);

        $this->fire('compact');

        $this->assertDirectoryExists($stale->sessionDir(), 'janitorial work belongs to a fresh session only');
    }
}
