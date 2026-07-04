<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Cli;

use JesseGall\CodeCommandments\Cli\HookResponse;
use PHPUnit\Framework\TestCase;

final class HookResponseTest extends TestCase
{
    public function test_no_emissions_is_silence(): void
    {
        $this->assertNull(HookResponse::merge([], 'PostToolUse'));
    }

    public function test_a_block_wins_and_joins_the_reasons(): void
    {
        $merged = HookResponse::merge([
            ['decision' => 'block', 'reason' => 'did you judge?'],
            ['hookSpecificOutput' => ['additionalContext' => 'a stray inject']],
            ['decision' => 'block', 'reason' => 'keep going.'],
        ], 'Stop');

        $this->assertSame('block', $merged['decision']);
        $this->assertSame("did you judge?\n\nkeep going.", $merged['reason']);
    }

    public function test_injected_contexts_concatenate(): void
    {
        $merged = HookResponse::merge([
            ['suppressOutput' => true, 'hookSpecificOutput' => ['additionalContext' => 'trace to the source']],
            ['suppressOutput' => true, 'hookSpecificOutput' => ['additionalContext' => 'hold to the constraints']],
        ], 'PostToolUse');

        $this->assertSame('PostToolUse', $merged['hookSpecificOutput']['hookEventName']);
        $this->assertSame("trace to the source\n\nhold to the constraints", $merged['hookSpecificOutput']['additionalContext']);
        $this->assertTrue($merged['suppressOutput'], 'suppressed only because every context asked to be');
    }

    public function test_suppress_is_off_when_any_context_did_not_ask_for_it(): void
    {
        $merged = HookResponse::merge([
            ['suppressOutput' => true, 'hookSpecificOutput' => ['additionalContext' => 'a']],
            ['hookSpecificOutput' => ['additionalContext' => 'b']],
        ], 'PostToolUse');

        $this->assertArrayNotHasKey('suppressOutput', $merged);
        $this->assertSame("a\n\nb", $merged['hookSpecificOutput']['additionalContext']);
    }

    public function test_empty_contexts_are_ignored(): void
    {
        $this->assertNull(HookResponse::merge([
            ['hookSpecificOutput' => ['additionalContext' => '']],
            ['suppressOutput' => true],
        ], 'PostToolUse'));
    }
}
