<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Cli;

use JesseGall\CodeCommandments\Hooks\HookDispatch;
use JesseGall\CodeCommandments\Cli\Input;
use JesseGall\CodeCommandments\Cli\Plan\PlanMarker;
use JesseGall\CodeCommandments\Workspace;
use PHPUnit\Framework\TestCase;

/**
 * The one entry point every wired moment runs through: it fans out to the whole registry, merges what
 * the handlers emit, and stays silent when nothing applies. Driven through a {@see CapturingHookIO} +
 * {@see FakeGit}, so no STDIN, harness, or real repository.
 */
final class HookDispatchTest extends TestCase
{
    private string $root;

    private string|false $priorProjectDir;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/cc-dispatch-' . uniqid('', true);
        mkdir($this->root . '/.commandments', 0777, true);
        // Scope the Remind heartbeat counter to this test's root so it's deterministic across runs.
        $this->priorProjectDir = getenv('CLAUDE_PROJECT_DIR');
        putenv('CLAUDE_PROJECT_DIR=' . $this->root);
    }

    protected function tearDown(): void
    {
        putenv($this->priorProjectDir === false ? 'CLAUDE_PROJECT_DIR' : 'CLAUDE_PROJECT_DIR=' . $this->priorProjectDir);
        exec('rm -rf ' . escapeshellarg($this->root));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<array<string, mixed>>
     */
    private function dispatch(array $payload): array
    {
        $io = new CapturingHookIO(new FakeGit($this->root, 'sha1', 'plan/x'), $payload);
        new HookDispatch($io)->run(Input::of('hooks'));

        return $io->emitted;
    }

    private function writeConfig(string $body): void
    {
        file_put_contents(
            $this->root . '/.commandments/config.php',
            "<?php\nuse JesseGall\\CodeCommandments\\Config;\nreturn function (Config \$config): void {\n    {$body}\n};\n",
        );
    }

    public function test_a_moment_no_handler_cares_about_is_silent(): void
    {
        $this->assertSame([], $this->dispatch(['hook_event_name' => 'PreToolUse', 'tool_name' => 'Read']));
    }

    public function test_post_tool_use_merges_every_reminder_into_one_context(): void
    {
        // A plan with a constraint makes BOTH the cardinal-rule Remind AND the ConstraintReminder fire on
        // the 25th tool use — the dispatcher merges them into ONE additionalContext.
        $this->writeConfig('$config->planExecution(fn ($p) => $p->constraint(\'No frontend logic.\'));');
        PlanMarker::inSession(Workspace::at($this->root))->activate('sha1');

        $emitted = [];

        for ($i = 0; $i < 25; $i++) {
            $emitted = $this->dispatch(['hook_event_name' => 'PostToolUse', 'tool_name' => 'Edit']);
        }

        $this->assertCount(1, $emitted, 'one merged response, not one per handler');
        $context = (string) ($emitted[0]['hookSpecificOutput']['additionalContext'] ?? '');
        $this->assertStringContainsString('trace every fix to its SOURCE', $context, 'the cardinal-rule reminder');
        $this->assertStringContainsString('No frontend logic.', $context, 'the constraint reminder');
    }

    public function test_stop_blocks_when_a_handler_blocks(): void
    {
        $this->writeConfig('$config->planExecution(fn ($p) => $p->keepGoing());');
        PlanMarker::inSession(Workspace::at($this->root))->activate('sha0');

        $emitted = $this->dispatch(['hook_event_name' => 'Stop']);

        $this->assertSame('block', $emitted[0]['decision'] ?? null);
        $this->assertStringContainsString("plan isn't finished", (string) ($emitted[0]['reason'] ?? ''));
    }

    public function test_stop_is_silent_while_parked_on_background_work(): void
    {
        $this->writeConfig('$config->planExecution(fn ($p) => $p->keepGoing());');
        PlanMarker::inSession(Workspace::at($this->root))->activate('sha0');

        $emitted = $this->dispatch([
            'hook_event_name' => 'Stop',
            'background_tasks' => [['id' => 'a', 'status' => 'running']],
        ]);

        $this->assertSame([], $emitted, 'the base-class guard suppresses every Stop handler');
    }

    public function test_stop_is_silent_in_plan_mode(): void
    {
        // In PLAN MODE the agent stops to PRESENT its plan for approval — with an active plan marker
        // (e.g. a re-plan mid-execution) the keep-going nudge must not hold that stop.
        $this->writeConfig('$config->planExecution(fn ($p) => $p->keepGoing());');
        PlanMarker::inSession(Workspace::at($this->root))->activate('sha0');

        $emitted = $this->dispatch(['hook_event_name' => 'Stop', 'permission_mode' => 'plan']);

        $this->assertSame([], $emitted, 'no Stop handler fires while planning');
    }

    public function test_every_hook_is_silent_inside_a_subagent(): void
    {
        // A read-only exploration subagent must receive none of our reminders/injections — they speak only
        // to the main session. The `agent_id` stamp makes even the 25th tool use (which normally fires the
        // cardinal-rule Remind) stay silent.
        $this->writeConfig('$config->planExecution(fn ($p) => $p->constraint(\'No frontend logic.\'));');
        PlanMarker::inSession(Workspace::at($this->root))->activate('sha1');

        $emitted = [['sentinel' => true]];

        for ($i = 0; $i < 25; $i++) {
            $emitted = $this->dispatch(['hook_event_name' => 'PostToolUse', 'tool_name' => 'Edit', 'agent_id' => 'sub-7']);
        }

        $this->assertSame([], $emitted, 'no reminder fires inside a subagent');
    }
}
