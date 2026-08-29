<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Hooks;

use JesseGall\CodeCommandments\Hooks\HookEvent;
use JesseGall\CodeCommandments\Hooks\HookResponse;
use PHPUnit\Framework\TestCase;

/**
 * The moments the journal is built on — the assistant message as it streams, the compaction boundary
 * either side of it, and the turn that just ended — read off the payload the harness really sends, and
 * the one reply a `PreCompact` may give besides blocking.
 */
final class CompactionPayloadTest extends TestCase
{
    /**
     * A real `MessageDisplay` payload, captured from Claude Code 2.1.251.
     *
     * @return array<string, mixed>
     */
    private function messageDisplay(): array
    {
        return [
            'hook_event_name' => 'MessageDisplay',
            'session_id' => 'sess-1',
            'transcript_path' => '/tmp/sess-1.jsonl',
            'cwd' => '/repo',
            'turn_id' => 'turn-1',
            'message_id' => 'msg-1',
            'index' => 0,
            'final' => true,
            'delta' => '[d] the delta is the message text',
        ];
    }

    public function test_it_reads_a_streaming_assistant_message(): void
    {
        $event = new HookEvent($this->messageDisplay(), '/repo');

        $this->assertSame('MessageDisplay', $event->name());
        $this->assertSame('[d] the delta is the message text', $event->delta());
        $this->assertSame('msg-1', $event->messageId());
        $this->assertSame('turn-1', $event->turnId());
        $this->assertSame('/tmp/sess-1.jsonl', $event->transcriptPath());
        $this->assertTrue($event->isFinalFlush());
    }

    /**
     * A message ending on a newline has an empty final delta, so the end of a message is what `final`
     * says and never what the delta looks like.
     */
    public function test_the_last_flush_of_a_message_is_final_even_when_its_delta_is_empty(): void
    {
        $event = new HookEvent([...$this->messageDisplay(), 'delta' => '', 'index' => 3], '/repo');

        $this->assertSame('', $event->delta());
        $this->assertTrue($event->isFinalFlush());
    }

    public function test_a_mid_message_flush_is_not_final(): void
    {
        $event = new HookEvent([...$this->messageDisplay(), 'final' => false], '/repo');

        $this->assertFalse($event->isFinalFlush());
    }

    public function test_it_reads_the_compaction_boundary_either_side(): void
    {
        $before = new HookEvent([
            'hook_event_name' => 'PreCompact',
            'trigger' => 'auto',
            'custom_instructions' => null,
            'transcript_path' => '/tmp/sess-1.jsonl',
        ], '/repo');

        $after = new HookEvent([
            'hook_event_name' => 'PostCompact',
            'trigger' => 'auto',
            'compact_summary' => 'the conversation, rewritten',
        ], '/repo');

        $this->assertSame('auto', $before->trigger());
        $this->assertSame('auto', $after->trigger());
        $this->assertSame('the conversation, rewritten', $after->compactSummary());
        $this->assertSame('', $before->compactSummary());
    }

    /**
     * The user's own `/compact` reports a different trigger, which is what lets a hook bind to automatic
     * compaction alone.
     */
    public function test_a_manual_compaction_names_itself(): void
    {
        $event = new HookEvent(['hook_event_name' => 'PreCompact', 'trigger' => 'manual'], '/repo');

        $this->assertSame('manual', $event->trigger());
    }

    public function test_a_stop_carries_the_message_that_was_ending(): void
    {
        $event = new HookEvent([
            'hook_event_name' => 'Stop',
            'stop_hook_active' => false,
            'last_assistant_message' => '[e] finished the reader',
        ], '/repo');

        $this->assertSame('[e] finished the reader', $event->lastAssistantMessage());
    }

    public function test_a_prompt_carries_the_users_own_words(): void
    {
        $event = new HookEvent([
            'hook_event_name' => 'UserPromptSubmit',
            'prompt' => 'motion.ts is FORBIDDEN',
        ], '/repo');

        $this->assertSame('motion.ts is FORBIDDEN', $event->prompt());
    }

    /**
     * Every accessor answers for a payload that carries none of it — a manual CLI run has no event at all.
     */
    public function test_an_empty_payload_answers_every_question(): void
    {
        $event = new HookEvent([], '/repo');

        $this->assertSame('', $event->transcriptPath());
        $this->assertSame('', $event->trigger());
        $this->assertSame('', $event->compactSummary());
        $this->assertSame('', $event->lastAssistantMessage());
        $this->assertSame('', $event->prompt());
        $this->assertSame('', $event->messageId());
        $this->assertSame('', $event->turnId());
        $this->assertSame('', $event->delta());
        $this->assertFalse($event->isFinalFlush());
    }

    /**
     * The harness takes a `PreCompact`'s stdout verbatim, so the instructions travel as raw text.
     */
    public function test_compaction_instructions_travel_unencoded(): void
    {
        $response = HookResponse::instructing('  keep the rulings, not just the actions  ');

        $this->assertFalse($response->isSilent());
        $this->assertSame('keep the rulings, not just the actions', $response->json('PreCompact'));
    }

    public function test_empty_instructions_are_silence(): void
    {
        $this->assertTrue(HookResponse::instructing('   ')->isSilent());
    }

    /**
     * Several hooks' instructions join the way the harness joins them itself.
     */
    public function test_instructions_merge_into_one_reply(): void
    {
        $merged = HookResponse::merge([
            HookResponse::instructing('the pinned facts'),
            HookResponse::silent(),
            HookResponse::instructing('the open span'),
        ]);

        $this->assertSame("the pinned facts\n\nthe open span", $merged->json('PreCompact'));
    }

    /**
     * A blocked hook contributes no instructions — the harness drops the output of one that blocked, and
     * cancelling the compaction leaves nothing to instruct.
     */
    public function test_a_block_wins_over_instructions(): void
    {
        $merged = HookResponse::merge([
            HookResponse::instructing('the pinned facts'),
            HookResponse::blocking('file anything important first'),
        ]);

        $this->assertStringContainsString('"decision":"block"', $merged->json('PreCompact'));
    }
}
