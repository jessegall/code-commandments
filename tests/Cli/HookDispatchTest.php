<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Cli;

use JesseGall\CodeCommandments\Cli\Hooks\HookDispatch;
use JesseGall\CodeCommandments\Cli\Input;
use JesseGall\CodeCommandments\Tests\Concerns\TemporaryProject;
use PHPUnit\Framework\TestCase;

/**
 * The one entry point every wired moment runs through: it fans out to the whole registry, merges what
 * the handlers emit, and stays silent when nothing applies. Driven through a {@see CapturingHookIO} +
 * {@see FakeGit}, so no STDIN, harness, or real repository.
 */
final class HookDispatchTest extends TestCase
{
    use TemporaryProject;

    /**
     * @param  array<string, mixed>  $payload
     * @return list<array<string, mixed>>
     */
    private function dispatch(array $payload): array
    {
        $io = new CapturingHookIO(new FakeGit($this->root, 'sha1', 'feature/x'), $payload);
        new HookDispatch($io)->run(Input::of('hooks'));

        return $io->emitted;
    }

    public function test_a_moment_no_handler_cares_about_is_silent(): void
    {
        $this->assertSame([], $this->dispatch(['hook_event_name' => 'PreToolUse', 'tool_name' => 'Read']));
    }

    public function test_a_compaction_merges_every_recall_into_one_context(): void
    {
        // Two hooks re-surfacing something on the same SessionStart — the dispatcher merges them into
        // ONE additionalContext.
        $this->writeConfig('$config->hook(\JesseGall\CodeCommandments\Tests\Cli\FirstRecall::class, \JesseGall\CodeCommandments\Tests\Cli\SecondRecall::class);');

        $emitted = $this->dispatch(['hook_event_name' => 'SessionStart', 'source' => 'compact']);
        $context = $emitted === [] ? '' : $emitted[0]->context->unwrapOr('');

        $this->assertCount(1, $emitted, 'one merged response, not one per handler');
        $this->assertStringContainsString('No frontend logic.', $context, 'the first recall');
        $this->assertStringContainsString('Write tests each phase.', $context, 'the second recall');
    }

    /**
     * The rule the whole reminder surface now keeps: an ordinary edit that broke nothing hears NOTHING.
     * A message that arrives unprompted is read once and skimmed after, and it takes the ones that do
     * report a real violation with it.
     */
    public function test_an_ordinary_edit_that_breaks_no_rule_is_silent(): void
    {
        for ($i = 0; $i < 60; $i++) {
            $this->assertSame([], $this->dispatch(['hook_event_name' => 'PostToolUse', 'tool_name' => 'Read']), "silent on tool use {$i}");
        }
    }

    public function test_stop_blocks_when_a_handler_blocks(): void
    {
        $this->writeConfig('$config->hook(\JesseGall\CodeCommandments\Tests\Cli\StopBlockingHook::class);');

        $emitted = $this->dispatch(['hook_event_name' => 'Stop']);

        $this->assertTrue($emitted[0]->blockReason->isSome());
        $this->assertStringContainsString('the work is not finished', $emitted[0]->blockReason->unwrapOr(''));
    }

    public function test_stop_is_silent_while_parked_on_background_work(): void
    {
        $this->writeConfig('$config->hook(\JesseGall\CodeCommandments\Tests\Cli\StopBlockingHook::class);');

        $emitted = $this->dispatch([
            'hook_event_name' => 'Stop',
            'background_tasks' => [['id' => 'a', 'status' => 'running']],
        ]);

        $this->assertSame([], $emitted, 'the base-class guard suppresses every Stop handler');
    }

    public function test_stop_is_silent_in_plan_mode(): void
    {
        // In PLAN MODE the agent stops to PRESENT its plan for approval — a hook that holds every other
        // stop must not hold that one.
        $this->writeConfig('$config->hook(\JesseGall\CodeCommandments\Tests\Cli\StopBlockingHook::class);');

        $emitted = $this->dispatch(['hook_event_name' => 'Stop', 'permission_mode' => 'plan']);

        $this->assertSame([], $emitted, 'no Stop handler fires while planning');
    }

    /**
     * What a subagent hears is decided by the {@see Discipline} marker, not by being a subagent. A rule
     * about the CODE is true whoever holds it — a worker has LESS context than the orchestrator, not more
     * — so a discipline about the edit in front of it reaches it, while a recall belonging to the
     * orchestrator's session does not.
     */
    public function test_a_subagent_hears_the_disciplines_and_nothing_else(): void
    {
        // The `agent_id` stamp is what marks it a worker.

        $edit = ['hook_event_name' => 'PreToolUse', 'tool_name' => 'Edit', 'agent_id' => 'sub-7'];
        $edit['tool_input'] = ['file_path' => $this->root . '/tests/Unit/ThingTest.php'];

        $emitted = $this->dispatch($edit);
        $context = $emitted === [] ? '' : $emitted[0]->context->unwrapOr('');

        $this->assertStringContainsString('trace to the source', $context, 'a discipline reaches a worker — it is writing code too');
    }

    /**
     * The other side of the same marker: a recall belongs to the session that owns the work, so a
     * worker's compaction hears nothing, while the orchestrator's does.
     */
    public function test_a_subagent_does_not_hear_the_orchestrators_recalls(): void
    {
        $this->writeConfig('$config->hook(\JesseGall\CodeCommandments\Tests\Cli\FirstRecall::class);');

        $compact = ['hook_event_name' => 'SessionStart', 'source' => 'compact'];

        $this->assertSame([], $this->dispatch($compact + ['agent_id' => 'sub-7']), "the worker never hears the orchestrator's recall");

        $emitted = $this->dispatch($compact);
        $this->assertStringContainsString('No frontend logic.', $emitted[0]->context->unwrapOr(''), 'the orchestrator does');
    }
}
