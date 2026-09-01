<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Cli;

use JesseGall\CodeCommandments\Cli\Hooks\HookDispatch;
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
        // Scope the hook counters to this test's root so they're deterministic across runs.
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

    public function test_a_compaction_merges_every_recall_into_one_context(): void
    {
        // A compaction continuing a plan makes BOTH the ConstraintReminder and the TestingReminder fire on
        // the same SessionStart — the dispatcher merges them into ONE additionalContext.
        $this->writeConfig('$config->planExecution(fn ($p) => $p->constraint(\'No frontend logic.\')->testFlow(\'Write tests each phase.\'));');
        PlanMarker::inSession(Workspace::at($this->root))->activate('sha1');

        $emitted = $this->dispatch(['hook_event_name' => 'SessionStart', 'source' => 'compact']);
        $context = $emitted === [] ? '' : $emitted[0]->context->unwrapOr('');

        $this->assertCount(1, $emitted, 'one merged response, not one per handler');
        $this->assertStringContainsString('No frontend logic.', $context, 'the constraint recall');
        $this->assertStringContainsString('Write tests each phase.', $context, 'the testing-methodology recall');
    }

    /**
     * The rule the whole reminder surface now keeps: an ordinary edit that broke nothing hears NOTHING.
     * A message that arrives unprompted is read once and skimmed after, and it takes the ones that do
     * report a real violation with it.
     */
    public function test_an_ordinary_edit_that_breaks_no_rule_is_silent(): void
    {
        $this->writeConfig('$config->planExecution(fn ($p) => $p->constraint(\'No frontend logic.\')->testFlow(\'Write tests each phase.\'));');
        PlanMarker::inSession(Workspace::at($this->root))->activate('sha1');

        for ($i = 0; $i < 60; $i++) {
            $this->assertSame([], $this->dispatch(['hook_event_name' => 'PostToolUse', 'tool_name' => 'Read']), "silent on tool use {$i}");
        }
    }

    public function test_stop_blocks_when_a_handler_blocks(): void
    {
        $this->writeConfig('$config->planExecution(fn ($p) => $p->keepGoing());');
        PlanMarker::inSession(Workspace::at($this->root))->activate('sha0');

        $emitted = $this->dispatch(['hook_event_name' => 'Stop']);

        $this->assertTrue($emitted[0]->blockReason->isSome());
        $this->assertStringContainsString("plan isn't finished", $emitted[0]->blockReason->unwrapOr(''));
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

    /**
     * What a subagent hears is decided by the {@see Discipline} marker, not by being a subagent. A rule
     * about the CODE is true whoever holds it — a worker has LESS context than the orchestrator, not more
     * — so a discipline about the edit in front of it reaches it, while a constraint belonging to the
     * orchestrator's plan does not.
     */
    public function test_a_subagent_hears_the_disciplines_and_nothing_else(): void
    {
        // The `agent_id` stamp is what marks it a worker.
        $this->writeConfig('$config->planExecution(fn ($p) => $p->constraint(\'No frontend logic.\'));');
        PlanMarker::inSession(Workspace::at($this->root))->activate('sha1');

        $edit = ['hook_event_name' => 'PreToolUse', 'tool_name' => 'Edit', 'agent_id' => 'sub-7'];
        $edit['tool_input'] = ['file_path' => $this->root . '/tests/Unit/ThingTest.php'];

        $emitted = $this->dispatch($edit);
        $context = $emitted === [] ? '' : $emitted[0]->context->unwrapOr('');

        $this->assertStringContainsString('trace to the source', $context, 'a discipline reaches a worker — it is writing code too');
    }

    /**
     * The other side of the same marker: the plan's constraints belong to the session that owns the plan,
     * so a worker's compaction hears nothing, while the orchestrator's does.
     */
    public function test_a_subagent_does_not_hear_the_orchestrators_plan_constraints(): void
    {
        $this->writeConfig('$config->planExecution(fn ($p) => $p->constraint(\'No frontend logic.\'));');
        PlanMarker::inSession(Workspace::at($this->root))->activate('sha1');

        $compact = ['hook_event_name' => 'SessionStart', 'source' => 'compact'];

        $this->assertSame([], $this->dispatch($compact + ['agent_id' => 'sub-7']), "the worker never hears the orchestrator's plan");

        $emitted = $this->dispatch($compact);
        $this->assertStringContainsString('No frontend logic.', $emitted[0]->context->unwrapOr(''), 'the orchestrator does');
    }
}
