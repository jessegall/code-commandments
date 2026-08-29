<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Cli\Journal;

use JesseGall\CodeCommandments\Cli\Journal\Session;
use JesseGall\CodeCommandments\Cli\Journal\Sessions;
use JesseGall\CodeCommandments\Cli\State\StateFile;
use PHPUnit\Framework\TestCase;

/**
 * Choosing which session to read. A hook knows its own; a human at a terminal knows none, so they are
 * shown the list and mount one — and the list comes from the transcripts, so a session that ran long
 * before any of this existed can still be read back.
 */
final class SessionsTest extends TestCase
{
    private string $home;

    private string $root = '/repo/thing';

    private string|false $priorHome;

    protected function setUp(): void
    {
        $this->home = sys_get_temp_dir() . '/cc-sessions-' . uniqid('', true);
        $transcripts = $this->home . '/.claude/projects/' . str_replace('/', '-', $this->root);
        mkdir($transcripts, 0777, true);

        $this->write($transcripts . '/aaaa1111-old.jsonl', 'the older session', 'Older work', 1000);
        $this->write($transcripts . '/bbbb2222-new.jsonl', 'the newer session', 'Newer work', 2000);

        $this->priorHome = getenv('HOME');
        putenv('HOME=' . $this->home);
    }

    protected function tearDown(): void
    {
        putenv($this->priorHome === false ? 'HOME' : 'HOME=' . $this->priorHome);
        exec('rm -rf ' . escapeshellarg($this->home));
    }

    private function write(string $path, string $prompt, string $title, int $at): void
    {
        file_put_contents($path, implode("\n", [
            json_encode(['type' => 'user', 'promptSource' => 'typed', 'message' => ['content' => $prompt]]),
            json_encode(['type' => 'ai-title', 'aiTitle' => $title]),
        ]) . "\n");

        touch($path, $at);
    }

    private function sessions(): Sessions
    {
        return new Sessions($this->root, new StateFile($this->home . '/mount', Sessions::legend()));
    }

    public function test_it_lists_a_projects_sessions_newest_first(): void
    {
        $all = $this->sessions()->all();

        $this->assertCount(2, $all);
        $this->assertSame('bbbb2222-new', $all[0]->id);
        $this->assertSame('Newer work', $all[0]->name);
    }

    /**
     * A menu prints a short id, so a short id is what a person types back.
     */
    public function test_a_session_answers_to_a_prefix_of_its_id(): void
    {
        $this->assertSame('aaaa1111-old', $this->sessions()->named('aaaa')->unwrap()->id);
        $this->assertTrue($this->sessions()->named('zzzz')->isNone());
    }

    /**
     * A session wears two names — the id its transcript is called after, and the hashed folder its state
     * lives in. A person reading either off their screen must not have to know which they are holding.
     */
    public function test_a_session_answers_to_its_state_folder_as_well_as_its_id(): void
    {
        $session = $this->sessions()->named('aaaa')->unwrap();

        $this->assertSame($session->id, $this->sessions()->named($session->key())->unwrap()->id);
        $this->assertNotSame($session->key(), substr($session->id, 0, 5), 'the folder is a hash, not a prefix');
    }

    /**
     * Nothing mounted is not an error — it is the moment to show the list rather than guess.
     */
    public function test_nothing_is_mounted_until_a_session_is_chosen(): void
    {
        $this->assertTrue($this->sessions()->mounted()->isNone());
    }

    public function test_a_mounted_session_is_what_every_later_command_reads(): void
    {
        $sessions = $this->sessions();
        $sessions->mount($sessions->named('aaaa')->unwrap());

        $this->assertSame('aaaa1111-old', $this->sessions()->mounted()->unwrap()->id);
        $this->assertSame('aaaa1111-old', $this->sessions()->current()->unwrap()->id);
    }

    /**
     * A session with no title falls back to the first thing the user actually said.
     */
    public function test_a_session_is_named_by_what_was_said_when_it_has_no_title(): void
    {
        $path = $this->home . '/.claude/projects/' . str_replace('/', '-', $this->root) . '/cccc3333-bare.jsonl';
        file_put_contents($path, json_encode(['type' => 'user', 'promptSource' => 'typed', 'message' => ['content' => 'fix the drilldown']]) . "\n");
        touch($path, 3000);

        $this->assertSame('fix the drilldown', $this->sessions()->all()[0]->name);
    }

    public function test_a_session_describes_itself_for_a_menu(): void
    {
        $described = new Session('abc', '/x.jsonl', 0, '')->describe();

        $this->assertStringContainsString('(nothing said yet)', $described);
    }
}
