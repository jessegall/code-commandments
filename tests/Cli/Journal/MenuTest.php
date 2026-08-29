<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Cli\Journal;

use JesseGall\CodeCommandments\Cli\Console;
use JesseGall\CodeCommandments\Cli\Journal\Menu;
use JesseGall\CodeCommandments\Cli\Journal\Sessions;
use JesseGall\CodeCommandments\Cli\State\StateFile;
use PHPUnit\Framework\TestCase;

/**
 * Reading a journal by hand. The menu is driven here the way a person drives it — answers in, screen out —
 * which is the only way to know it asks what it means to ask and stops when told to.
 */
final class MenuTest extends TestCase
{
    private string $home;

    private string $root = '/repo/read';

    private string|false $priorHome;

    protected function setUp(): void
    {
        $this->home = sys_get_temp_dir() . '/cc-menu-' . uniqid('', true);
        $transcripts = $this->home . '/.claude/projects/' . str_replace('/', '-', $this->root);
        mkdir($transcripts, 0777, true);

        file_put_contents($transcripts . '/aaaa1111-one.jsonl', implode("\n", [
            json_encode(['type' => 'ai-title', 'aiTitle' => 'The session in question']),
            json_encode(['type' => 'user', 'promptSource' => 'typed', 'message' => ['content' => 'motion.ts is FORBIDDEN']]),
            json_encode(['type' => 'assistant', 'message' => ['content' => [['type' => 'text', 'text' => '[!start] deleting it']]]]),
            json_encode(['type' => 'assistant', 'message' => ['content' => [['type' => 'text', 'text' => '[!discovery] one file imports it']]]]),
        ]) . "\n");

        $this->priorHome = getenv('HOME');
        putenv('HOME=' . $this->home);
    }

    protected function tearDown(): void
    {
        putenv($this->priorHome === false ? 'HOME' : 'HOME=' . $this->priorHome);
        exec('rm -rf ' . escapeshellarg($this->home));
    }

    /**
     * Drive the menu with $answers and return everything it put on screen.
     *
     * @param  list<string>  $answers
     */
    private function drive(array $answers): string
    {
        $in = fopen('php://memory', 'r+');
        fwrite($in, implode("\n", $answers) . "\n");
        rewind($in);

        $out = fopen('php://memory', 'r+');
        $sessions = new Sessions($this->root, new StateFile($this->home . '/mount', Sessions::legend()));

        new Menu($sessions, $this->root, new Console($out), $in)->run();

        rewind($out);

        return (string) stream_get_contents($out);
    }

    /**
     * With nothing mounted the reader is shown the sessions and picks one, rather than being guessed at.
     */
    public function test_it_asks_which_session_before_anything_else(): void
    {
        $screen = $this->drive(['1', 'q']);

        $this->assertStringContainsString('The session in question', $screen);
        $this->assertStringContainsString('── journal', $screen);
    }

    public function test_it_reads_the_last_messages_when_asked(): void
    {
        $screen = $this->drive(['1', '1', 'q']);

        $this->assertStringContainsString('motion.ts is FORBIDDEN', $screen);
        $this->assertStringContainsString('[!discovery] one file imports it', $screen);
    }

    public function test_it_reads_only_the_user_when_asked(): void
    {
        $screen = $this->drive(['1', '4', 'q']);

        $this->assertStringContainsString('motion.ts is FORBIDDEN', $screen);
        $this->assertStringNotContainsString('[!discovery]', $screen);
    }

    /**
     * Work started and never closed is the one thing in a session that is live state.
     */
    public function test_it_shows_work_left_open(): void
    {
        $this->assertStringContainsString('work left open', $this->drive(['1', '6', 'q']));
    }

    public function test_it_searches(): void
    {
        $screen = $this->drive(['1', '7', 'FORBIDDEN', 'q']);

        $this->assertStringContainsString('search for:', $screen);
        $this->assertStringContainsString('motion.ts is FORBIDDEN', $screen);
    }

    /**
     * A menu that did not stop when told to would trap the reader in it.
     */
    public function test_it_stops_when_told_to(): void
    {
        $this->assertStringContainsString('── journal', $this->drive(['1', 'q']));
    }

    /**
     * An answer that is not on offer is said so, rather than silently doing nothing.
     */
    public function test_an_unknown_answer_says_so(): void
    {
        $this->assertStringContainsString('(not an option)', $this->drive(['1', 'z', 'q']));
    }
}
