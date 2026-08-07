<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Cli;

use JesseGall\CodeCommandments\Hooks\HookResponse;
use PHPUnit\Framework\TestCase;

final class HookResponseTest extends TestCase
{
    public function test_no_responses_is_silence(): void
    {
        $this->assertTrue(HookResponse::merge([])->isSilent());
    }

    public function test_a_block_wins_and_joins_the_reasons(): void
    {
        $merged = HookResponse::merge([
            HookResponse::blocking('did you judge?'),
            HookResponse::injecting('a stray inject'),
            HookResponse::blocking('keep going.'),
        ]);

        $this->assertSame("did you judge?\n\nkeep going.", $merged->blockReason->unwrapOr(''));
        $this->assertTrue($merged->context->isNone(), 'a block carries no context');
    }

    public function test_injected_contexts_concatenate(): void
    {
        $merged = HookResponse::merge([
            HookResponse::injecting('trace to the source', quietly: true),
            HookResponse::injecting('hold to the constraints', quietly: true),
        ]);

        $this->assertSame("trace to the source\n\nhold to the constraints", $merged->context->unwrapOr(''));
        $this->assertTrue($merged->suppressOutput, 'suppressed only because every context asked to be');
    }

    public function test_suppress_is_off_when_any_context_did_not_ask_for_it(): void
    {
        $merged = HookResponse::merge([
            HookResponse::injecting('a', quietly: true),
            HookResponse::injecting('b'),
        ]);

        $this->assertFalse($merged->suppressOutput);
        $this->assertSame("a\n\nb", $merged->context->unwrapOr(''));
    }

    public function test_the_wire_shape_is_the_harness_protocol(): void
    {
        // The ONE place the protocol is spelled, so this is what pins it.
        $this->assertSame(
            '{"decision":"block","reason":"keep going."}',
            trim(HookResponse::blocking('keep going.')->json('Stop')),
        );

        $this->assertSame(
            '{"hookSpecificOutput":{"hookEventName":"PostToolUse","additionalContext":"trace to the source"},"suppressOutput":true}',
            trim(HookResponse::injecting('trace to the source', quietly: true)->json('PostToolUse')),
        );

        $this->assertSame(
            '{"hookSpecificOutput":{"hookEventName":"Stop","additionalContext":"read it back"}}',
            trim(HookResponse::injecting('read it back')->json('Stop')),
        );
    }

    public function test_a_silent_response_is_silent(): void
    {
        $this->assertTrue(HookResponse::silent()->isSilent());
        $this->assertFalse(HookResponse::injecting('something')->isSilent());
    }
}
