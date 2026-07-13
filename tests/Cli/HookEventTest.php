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
}
