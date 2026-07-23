<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Cli;

use JesseGall\CodeCommandments\Hooks\HookEvent;
use PHPUnit\Framework\TestCase;

final class HookEventTest extends TestCase
{
    public function test_session_id_comes_from_the_payload(): void
    {
        $event = new HookEvent(['session_id' => 'abc-123'], '/tmp/project');

        $this->assertSame('abc-123', $event->sessionId());
    }

    public function test_a_manual_run_has_no_session_id(): void
    {
        $this->assertSame('', new HookEvent([], '/tmp/project')->sessionId());
    }

    public function test_workspace_scopes_state_to_the_payload_session(): void
    {
        $event = new HookEvent(['session_id' => 'abc-123'], '/tmp/project');
        $key = substr(sha1('abc-123'), 0, 5);

        $this->assertSame("/tmp/project/.commandments/sessions/{$key}/.plan-active", $event->workspace()->path('.plan-active'));
    }

    public function test_workspace_without_a_session_uses_the_default_folder(): void
    {
        $event = new HookEvent([], '/tmp/project');

        $this->assertSame('/tmp/project/.commandments/sessions/default/sins.md', $event->workspace()->path('sins.md'));
    }

    public function test_a_subagent_payload_is_detected_by_agent_id_or_agent_type(): void
    {
        $this->assertTrue(new HookEvent(['agent_id' => 'sub-1'], '/tmp/p')->isSubagent());
        $this->assertTrue(new HookEvent(['agent_type' => 'Explore'], '/tmp/p')->isSubagent());
    }

    public function test_the_main_session_is_not_a_subagent(): void
    {
        // No agent fields (or empty ones) — the main coding session and a manual CLI run both read as
        // NOT a subagent, so the additive guard never changes existing behaviour.
        $this->assertFalse(new HookEvent(['session_id' => 'abc'], '/tmp/p')->isSubagent());
        $this->assertFalse(new HookEvent(['agent_id' => '', 'agent_type' => ''], '/tmp/p')->isSubagent());
        $this->assertFalse(new HookEvent([], '/tmp/p')->isSubagent());
    }

    public function test_plan_mode_is_read_from_the_permission_mode_field(): void
    {
        // Only the literal `plan` counts; every other mode — and an absent field (older Claude Code,
        // a manual CLI run) — reads as not-plan-mode, so the additive guard never changes behaviour.
        $this->assertTrue(new HookEvent(['permission_mode' => 'plan'], '/tmp/p')->isPlanMode());
        $this->assertFalse(new HookEvent(['permission_mode' => 'default'], '/tmp/p')->isPlanMode());
        $this->assertFalse(new HookEvent(['permission_mode' => 'acceptEdits'], '/tmp/p')->isPlanMode());
        $this->assertFalse(new HookEvent([], '/tmp/p')->isPlanMode());
    }
}
