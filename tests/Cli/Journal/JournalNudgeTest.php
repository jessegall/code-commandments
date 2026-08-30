<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Cli\Journal;

use JesseGall\CodeCommandments\Cli\Journal\Entry;
use JesseGall\CodeCommandments\Cli\Journal\Journal;
use JesseGall\CodeCommandments\Cli\Journal\Kind;
use JesseGall\CodeCommandments\Cli\Journal\Tag;
use JesseGall\CodeCommandments\Hooks\Handlers\JournalReminder;
use JesseGall\CodeCommandments\Hooks\HookResponse;
use JesseGall\CodeCommandments\Hooks\RecordingHookIO;
use JesseGall\CodeCommandments\Tests\Cli\FakeGit;
use JesseGall\CodeCommandments\Workspace;
use JesseGall\PhpTypes\Option;
use PHPUnit\Framework\TestCase;

/**
 * The nudge counts SILENCE, not time. It fired every 25 tool calls regardless, re-printing the whole tag
 * vocabulary — a reminder that arrives with nothing new in it, which is what teaches a reader to skim the
 * block that will eventually hold something. One session produced three tags in five hours under it.
 *
 * The response is GRADUATED: quiet, then one line per stretch of ten, then a refusal once the silence is
 * long enough that the reasoning in it is already gone. Each tier is asserted by its COUNT over a run of
 * calls, because "does it speak" and "does it speak once" are different questions and only the second one
 * was ever the bug.
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
     * $times tool calls, and everything the hook said across them — responses, not strings, because a
     * NUDGE and a BLOCK are the two things this has to tell apart and both carry text.
     *
     * @return list<HookResponse>
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
                $said[] = $response;
            }
        }

        return $said;
    }

    /**
     * The injected lines only — what it SAID without stopping anything.
     *
     * @param  list<HookResponse>  $said
     * @return list<string>
     */
    private function nudges(array $said): array
    {
        $lines = [];

        foreach ($said as $response) {
            foreach ($response->context as $context) {
                $lines[] = $context;
            }
        }

        return $lines;
    }

    /**
     * The refusals only — what it said INSTEAD of letting the call through.
     *
     * @param  list<HookResponse>  $said
     * @return list<string>
     */
    private function blocks(array $said): array
    {
        $reasons = [];

        foreach ($said as $response) {
            foreach ($response->blockReason as $reason) {
                $reasons[] = $reason;
            }
        }

        return $reasons;
    }

    public function test_it_stays_quiet_while_the_silence_is_short(): void
    {
        $this->assertSame([], $this->calls(9));
    }

    /**
     * ONE line, and it says its own numbers — "1 tag this session, nothing in the last 10 calls" is a
     * fact to act on where "remember to tag" is wallpaper.
     */
    public function test_it_speaks_once_the_silence_is_long_enough_and_names_its_numbers(): void
    {
        $this->records('[!discovery] the enum was never the cause', Tag::Discovery);

        $said = $this->calls(10);
        $nudges = $this->nudges($said);

        $this->assertCount(1, $nudges, 'once per stretch, not once per call past the threshold');
        $this->assertSame([], $this->blocks($said), 'ten quiet calls is a nudge, not a refusal');
        $this->assertStringContainsString('1 tag', $nudges[0]);
        $this->assertStringContainsString('10 tool calls', $nudges[0]);
    }

    /**
     * Nagging every call past the threshold is the metronome the debt-and-payment shape exists to avoid.
     * It speaks at each MULTIPLE of the threshold — twice across twenty calls, not eleven times.
     */
    public function test_it_does_not_nag_every_call_once_past_the_threshold(): void
    {
        $said = $this->calls(20);

        $this->assertCount(2, $this->nudges($said), 'twenty quiet calls earn one nudge per stretch of ten');
        $this->assertSame([], $this->blocks($said));
    }

    /**
     * RECORDING something clears the debt — not the nudge firing. A counter reset by its own nudge is a
     * metronome; one reset by the thing it asks for is a debt that clears when paid.
     */
    public function test_recording_something_clears_the_debt(): void
    {
        $this->calls(9);
        $this->records('[!update] where the work stands', Tag::Update);

        $this->assertSame([], $this->calls(9), 'the count started again from the tag');
    }

    /**
     * Untagged narration is not what it asked for — it is what the silence is made of.
     */
    public function test_untagged_narration_does_not_clear_the_debt(): void
    {
        $this->calls(9);
        $this->records('just talking about the work');

        $this->assertCount(1, $this->nudges($this->calls(1)), 'the tenth quiet call still earns the nudge');
    }

    /**
     * The vocabulary is not repeated. `journal instructions` holds it, and a reader who needs it can ask
     * once rather than be shown it forty times.
     */
    public function test_it_does_not_reprint_the_vocabulary(): void
    {
        $said = implode("\n", $this->nudges($this->calls(10)));

        $this->assertStringNotContainsString('[!blocked]', $said);
        $this->assertStringNotContainsString('[!end]', $said);
        $this->assertLessThan(400, strlen($said), 'one line, not a wall');
    }

    /**
     * The whole graduated response in one run, measured rather than remembered: twenty-seven quiet calls
     * are two nudges (ten, twenty) and then three refusals (twenty-five onwards). Asserting the two counts
     * together is what proves the tiers do not overlap — a nudge at twenty-five as well as a block would
     * pass either count on its own.
     */
    public function test_a_long_silence_earns_two_nudges_and_then_refuses_every_call(): void
    {
        $said = $this->calls(27);

        $this->assertCount(2, $this->nudges($said), 'one per stretch of ten, up to the point it stops asking');
        $this->assertCount(3, $this->blocks($said), 'and from twenty-five on, every call');
    }

    /**
     * Past the enforced threshold it is a refusal, not a line to skim — and it keeps refusing, because a
     * gate that lets the next call through has asked and not insisted.
     */
    public function test_a_long_silence_stops_asking_and_refuses(): void
    {
        $this->calls(49);

        $blocked = $this->blocks($this->calls(1));

        $this->assertCount(1, $blocked);
        $this->assertStringContainsString('50 tool calls', $blocked[0], 'it names the number it measured');
        $this->assertCount(1, $this->blocks($this->calls(1)), 'and the next call too');
    }

    /**
     * The refusal is CHEAP TO SATISFY — one recorded line clears it. A gate that could only be answered
     * by real work would be paid for in the thing it exists to protect.
     */
    public function test_recording_one_line_clears_the_refusal(): void
    {
        $this->calls(50);
        $this->records('[!discovery] the cursor was never written', Tag::Discovery);

        $this->assertSame([], $this->calls(9), 'the debt is paid and the count starts again');
    }
}
