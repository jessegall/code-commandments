<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Cli\Journal;

use JesseGall\CodeCommandments\Cli\Journal\Entry;
use JesseGall\CodeCommandments\Cli\Journal\Journal;
use JesseGall\CodeCommandments\Cli\Journal\Kind;
use JesseGall\CodeCommandments\Cli\Journal\Tag;
use JesseGall\CodeCommandments\Hooks\Handlers\JournalReminder;
use JesseGall\CodeCommandments\Hooks\RecordingHookIO;
use JesseGall\CodeCommandments\Tests\Cli\FakeGit;
use JesseGall\CodeCommandments\Workspace;
use JesseGall\PhpTypes\Option;
use PHPUnit\Framework\TestCase;

/**
 * The nudge counts SILENCE, not time. It fired every 25 tool calls regardless, re-printing the whole tag
 * vocabulary — a reminder that arrives with nothing new in it, which is what teaches a reader to skim the
 * block that will eventually hold something. One session produced three tags in five hours under it.
 */
final class JournalNudgeTest extends TestCase
{
    private string $root;

    private string|false $priorProjectDir;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/cc-nudge-' . uniqid('', true);
        mkdir($this->root . '/.commandments', 0777, true);
        $this->priorProjectDir = getenv('CLAUDE_PROJECT_DIR');
        putenv('CLAUDE_PROJECT_DIR=' . $this->root);
    }

    protected function tearDown(): void
    {
        putenv($this->priorProjectDir === false ? 'CLAUDE_PROJECT_DIR' : 'CLAUDE_PROJECT_DIR=' . $this->priorProjectDir);
        exec('rm -rf ' . escapeshellarg($this->root));
    }

    private function journal(): Journal
    {
        return Journal::inSession(Workspace::at($this->root, 'sess-1'));
    }

    private function records(string $text, ?Tag $tag = null): void
    {
        $this->journal()->file(new Entry(Kind::Agent, 'now', 't', 'm' . uniqid(), $tag === null ? Option::none() : Option::some($tag), $text));
    }

    /**
     * @return list<string>
     */
    private function calls(int $times): array
    {
        $said = [];

        for ($call = 0; $call < $times; $call++) {
            $io = new RecordingHookIO([
                'hook_event_name' => 'PostToolUse',
                'session_id' => 'sess-1',
                'tool_name' => 'Read',
            ], new FakeGit($this->root));

            new JournalReminder($io)->run([]);

            foreach ($io->emitted as $response) {
                $said[] = $response->context->unwrapOr('');
            }
        }

        return $said;
    }

    public function test_it_stays_quiet_while_the_silence_is_short(): void
    {
        $this->assertSame([], $this->calls(11));
    }

    /**
     * ONE line, and it says its own numbers — "3 tags in 12 tool calls" is a fact to act on where
     * "remember to tag" is wallpaper.
     */
    public function test_it_speaks_once_the_silence_is_long_enough_and_names_its_numbers(): void
    {
        $this->records('[!discovery] the enum was never the cause', Tag::Discovery);

        $said = $this->calls(12);

        $this->assertCount(1, $said, 'once per stretch, not once per call past the threshold');
        $this->assertStringContainsString('1 tag', $said[0]);
        $this->assertStringContainsString('12 tool calls', $said[0]);
    }

    /**
     * Nagging every call past the threshold is the metronome the debt-and-payment shape exists to avoid.
     */
    public function test_it_does_not_nag_every_call_once_past_the_threshold(): void
    {
        $this->assertCount(1, $this->calls(20), 'twenty quiet calls earn one nudge, not nine');
    }

    /**
     * RECORDING something clears the debt — not the nudge firing. A counter reset by its own nudge is a
     * metronome; one reset by the thing it asks for is a debt that clears when paid.
     */
    public function test_recording_something_clears_the_debt(): void
    {
        $this->calls(11);
        $this->records('[!update] where the work stands', Tag::Update);

        $this->assertSame([], $this->calls(11), 'the count started again from the tag');
    }

    /**
     * Untagged narration is not what it asked for — it is what the silence is made of.
     */
    public function test_untagged_narration_does_not_clear_the_debt(): void
    {
        $this->calls(11);
        $this->records('just talking about the work');

        $this->assertCount(1, $this->calls(1), 'the twelfth quiet call still earns the nudge');
    }

    /**
     * The vocabulary is not repeated. `journal instructions` holds it, and a reader who needs it can ask
     * once rather than be shown it forty times.
     */
    public function test_it_does_not_reprint_the_vocabulary(): void
    {
        $said = implode("\n", $this->calls(12));

        $this->assertStringNotContainsString('[!blocked]', $said);
        $this->assertStringNotContainsString('[!end]', $said);
        $this->assertLessThan(400, strlen($said), 'one line, not a wall');
    }
}
