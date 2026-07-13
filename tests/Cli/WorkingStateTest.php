<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Cli;

use JesseGall\CodeCommandments\Hooks\Handlers\WorkingState;
use JesseGall\CodeCommandments\Cli\Plan\PlanMarker;
use JesseGall\CodeCommandments\Workspace;
use PHPUnit\Framework\TestCase;

/**
 * The working-state discipline: while a plan is active AND the project opted into `trackWorkingState()`,
 * a PostToolUse heartbeat nudges a refresh every 25 tool uses, a PreCompact flush fires before compaction,
 * and a SessionStart on `compact`/`resume` re-injects the record. All silent otherwise. Driven through a
 * {@see CapturingHookIO} + {@see FakeGit}, so no harness, STDIN, or real repository is involved.
 */
final class WorkingStateTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/cc-wstate-' . uniqid('', true);
        mkdir($this->root . '/.commandments', 0777, true);
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->root));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<array<string, mixed>>
     */
    private function fire(array $payload): array
    {
        $io = new CapturingHookIO(new FakeGit($this->root), $payload);
        new WorkingState($io)->run([]);

        return $io->emitted;
    }

    private function context(array $emitted): string
    {
        return (string) ($emitted[0]['hookSpecificOutput']['additionalContext'] ?? '');
    }

    private function enable(): void
    {
        file_put_contents(
            $this->root . '/.commandments/config.php',
            "<?php\nuse JesseGall\\CodeCommandments\\Config;\nreturn function (Config \$config): void {\n"
            . "    \$config->planExecution(fn (\$p) => \$p->trackWorkingState());\n};\n",
        );
    }

    private function record(string $body): void
    {
        file_put_contents(Workspace::at($this->root)->path('.plan-working-state'), $body);
    }

    // --- Heartbeat -------------------------------------------------------------------------------

    public function test_heartbeat_nudges_a_refresh_once_every_interval_during_a_tracked_plan(): void
    {
        $this->enable();
        PlanMarker::inSession(Workspace::at($this->root))->activate('sha');

        for ($i = 1; $i < 25; $i++) {
            $this->assertSame([], $this->fire(['hook_event_name' => 'PostToolUse', 'tool_name' => 'Edit']), "silent on tick {$i}");
        }

        $context = $this->context($this->fire(['hook_event_name' => 'PostToolUse', 'tool_name' => 'Edit']));
        $this->assertStringContainsString('.plan-working-state', $context);
        $this->assertStringContainsString('WORKING STATE', $context);

        // Resets after firing — the next tick is silent again.
        $this->assertSame([], $this->fire(['hook_event_name' => 'PostToolUse', 'tool_name' => 'Edit']), 'counter reset after the nudge');
    }

    public function test_heartbeat_is_silent_when_the_toggle_is_off(): void
    {
        PlanMarker::inSession(Workspace::at($this->root))->activate('sha'); // active plan, but trackWorkingState not set

        for ($i = 0; $i < 30; $i++) {
            $this->assertSame([], $this->fire(['hook_event_name' => 'PostToolUse', 'tool_name' => 'Edit']));
        }
    }

    public function test_heartbeat_is_silent_when_no_plan_is_active(): void
    {
        $this->enable(); // toggle on, but no plan marker

        for ($i = 0; $i < 30; $i++) {
            $this->assertSame([], $this->fire(['hook_event_name' => 'PostToolUse', 'tool_name' => 'Edit']));
        }
    }

    // --- PreCompact flush ------------------------------------------------------------------------

    public function test_precompact_injects_a_flush_nudge_during_a_tracked_plan(): void
    {
        $this->enable();
        PlanMarker::inSession(Workspace::at($this->root))->activate('sha');

        $context = $this->context($this->fire(['hook_event_name' => 'PreCompact']));
        $this->assertStringContainsString('COMPACT', $context);
        $this->assertStringContainsString('.plan-working-state', $context);
    }

    public function test_precompact_is_silent_off_plan_or_off_toggle(): void
    {
        PlanMarker::inSession(Workspace::at($this->root))->activate('sha'); // no toggle
        $this->assertSame([], $this->fire(['hook_event_name' => 'PreCompact']));

        $this->enable(); // toggle, but clear the plan
        PlanMarker::inSession(Workspace::at($this->root))->clear();
        $this->assertSame([], $this->fire(['hook_event_name' => 'PreCompact']));
    }

    // --- Recall on compact/resume ----------------------------------------------------------------

    public function test_sessionstart_reinjects_the_record_on_compact(): void
    {
        $this->enable();
        PlanMarker::inSession(Workspace::at($this->root))->activate('sha');
        $this->record("## Decisions\nBind the workflow explicitly — rejected a fixture hook (too magic).\n");

        $context = $this->context($this->fire(['hook_event_name' => 'SessionStart', 'source' => 'compact']));
        $this->assertStringContainsString('rejected a fixture hook', $context, 'the record content is re-injected');
        $this->assertStringContainsString('ground truth', $context);
    }

    public function test_sessionstart_reinjects_on_resume_too(): void
    {
        $this->enable();
        PlanMarker::inSession(Workspace::at($this->root))->activate('sha');
        $this->record("phase 3 next\n");

        $this->assertStringContainsString('phase 3 next', $this->context($this->fire(['hook_event_name' => 'SessionStart', 'source' => 'resume'])));
    }

    public function test_sessionstart_is_silent_on_a_fresh_session(): void
    {
        $this->enable();
        PlanMarker::inSession(Workspace::at($this->root))->activate('sha');
        $this->record("something\n");

        // startup/clear are fresh sessions — SessionReset wipes; WorkingState must not re-inject stale state.
        $this->assertSame([], $this->fire(['hook_event_name' => 'SessionStart', 'source' => 'startup']));
        $this->assertSame([], $this->fire(['hook_event_name' => 'SessionStart', 'source' => 'clear']));
    }

    public function test_sessionstart_is_silent_when_there_is_no_record_yet(): void
    {
        $this->enable();
        PlanMarker::inSession(Workspace::at($this->root))->activate('sha');

        $this->assertSame([], $this->fire(['hook_event_name' => 'SessionStart', 'source' => 'compact']));
    }
}
