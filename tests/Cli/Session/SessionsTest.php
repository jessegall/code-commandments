<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Cli\Session;

use JesseGall\CodeCommandments\Cli\Session\Sessions;
use PHPUnit\Framework\TestCase;

/**
 * The sessions a project has had. The list comes from the TRANSCRIPTS, so a session that ran long before
 * this package was installed is still named — which is what lets `session list` say what a folder named
 * after a hash actually holds.
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
        return new Sessions($this->root);
    }

    public function test_it_lists_a_projects_sessions_newest_first(): void
    {
        $all = $this->sessions()->all();

        $this->assertCount(2, $all);
        $this->assertSame('bbbb2222-new', $all[0]->id);
        $this->assertSame('Newer work', $all[0]->name);
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
}
