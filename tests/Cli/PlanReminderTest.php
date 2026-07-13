<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Cli;

use JesseGall\CodeCommandments\Cli\Plan\PlanConstraints;
use JesseGall\CodeCommandments\Cli\Plan\PlanMarker;
use JesseGall\CodeCommandments\Cli\Plan\PlanTesting;
use JesseGall\CodeCommandments\Cli\Plan\PlanWorkingState;
use JesseGall\CodeCommandments\Hooks\Counter;
use JesseGall\CodeCommandments\Hooks\Handlers\PlanReminder;
use JesseGall\CodeCommandments\Workspace;
use PHPUnit\Framework\TestCase;

/**
 * The plan-reminder hook: it opens a plan on ExitPlanMode (marker + skill nudge) and keeps it going
 * on Stop — but only when the project opted into keepGoing, and never forever (the stuck-cap and
 * once-only policy bound it). Driven through a {@see CapturingHookIO} + {@see FakeGit}, so no
 * harness, STDIN, or real repository is involved.
 */
final class PlanReminderTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/cc-plan-' . uniqid('', true);
        @mkdir($this->root . '/.commandments', 0777, true);
    }

    protected function tearDown(): void
    {
        @unlink(Workspace::at($this->root)->path('.plan-active'));
        @unlink($this->root . '/.commandments/config.php');
        @rmdir($this->root . '/.commandments');
        @rmdir($this->root);
    }

    public function test_exit_plan_mode_activates_the_marker_and_nudges_to_load_the_skill(): void
    {
        $context = $this->context($this->fire(['hook_event_name' => 'PostToolUse', 'tool_name' => 'ExitPlanMode']));

        $this->assertStringContainsString('commandments-executing-plans', $context);
        $this->assertStringContainsString('checks complete', $context);
        $this->assertTrue($this->marker()->isActive(), 'a plan is now active');
    }

    public function test_the_approval_nudge_asks_for_constraints_and_lists_global_ones(): void
    {
        $this->writeConfig('$config->planExecution(fn ($p) => $p->constraint(\'No frontend logic.\'));');

        $context = $this->context($this->fire(['hook_event_name' => 'PostToolUse', 'tool_name' => 'ExitPlanMode']));

        $this->assertStringContainsString('AskUserQuestion', $context, 'the agent is told to ask the user');
        $this->assertStringContainsString('constraints add', $context);
        $this->assertStringContainsString('No frontend logic.', $context, 'the global constraint is listed as in force');
    }

    public function test_the_approval_nudge_asks_for_the_testing_methodology(): void
    {
        $context = $this->context($this->fire(['hook_event_name' => 'PostToolUse', 'tool_name' => 'ExitPlanMode']));

        $this->assertStringContainsString('Testing methodology', $context, 'the agent is told to ask about tests');
        $this->assertStringContainsString('testing set', $context, 'and to record the answer');
        $this->assertStringContainsString('each phase', $context, 'the standard methods are offered');
    }

    public function test_the_testing_question_offers_the_configured_test_flow_when_set(): void
    {
        $this->writeConfig('$config->planExecution(fn ($p) => $p->testFlow(\'Write tests each phase.\'));');

        $context = $this->context($this->fire(['hook_event_name' => 'PostToolUse', 'tool_name' => 'ExitPlanMode']));

        $this->assertStringContainsString("configured test flow: \"Write tests each phase.\"", $context);
    }

    public function test_the_approval_nudge_carries_the_mode_autonomy_bullet(): void
    {
        // BestEffort mode: the bullet says finish as much as possible, defer a blocker, and retry at the end.
        $this->writeConfig('$config->planExecution(fn ($p) => $p->mode(\JesseGall\CodeCommandments\PlanMode::BestEffort));');
        $best = $this->context($this->fire(['hook_event_name' => 'PostToolUse', 'tool_name' => 'ExitPlanMode']));
        $this->assertStringContainsString('BEST-EFFORT', $best);
        $this->assertStringContainsString('DEFERRED', $best);
        $this->assertStringContainsString('retry every deferred step', $best);
        $this->assertStringNotContainsString('plan stuck', $best, 'a never-stop mode never mentions plan stuck');

        // Relentless mode: the bullet forbids stopping/asking and tells the agent to skip blockers.
        $this->writeConfig('$config->planExecution(fn ($p) => $p->mode(\JesseGall\CodeCommandments\PlanMode::Relentless));');
        $relentless = $this->context($this->fire(['hook_event_name' => 'PostToolUse', 'tool_name' => 'ExitPlanMode']));
        $this->assertStringContainsString('RELENTLESS', $relentless);
        $this->assertStringContainsString('SKIP', $relentless);
        $this->assertStringNotContainsString('plan stuck', $relentless, 'relentless never mentions plan stuck');
    }

    public function test_the_approval_nudge_states_the_working_state_discipline_only_when_tracked(): void
    {
        // Off by default — the bullet is absent.
        $off = $this->context($this->fire(['hook_event_name' => 'PostToolUse', 'tool_name' => 'ExitPlanMode']));
        $this->assertStringNotContainsString('.plan-working-state', $off);

        $this->writeConfig('$config->planExecution(fn ($p) => $p->trackWorkingState());');

        $on = $this->context($this->fire(['hook_event_name' => 'PostToolUse', 'tool_name' => 'ExitPlanMode']));
        $this->assertStringContainsString('Working state', $on);
        $this->assertStringContainsString('.plan-working-state', $on);
        $this->assertStringContainsString('after each phase', $on);
    }

    public function test_approving_a_new_plan_resets_constraints_testing_and_counters(): void
    {
        // A previous plan left constraints, a testing choice, working-state notes and a bumped counter
        // behind. Approving a NEW plan resets the constraints/testing/counters — none may bleed in.
        $plan = new \JesseGall\CodeCommandments\PlanExecution()->build();
        PlanConstraints::inSession(Workspace::at($this->root), $plan)->addLocal('Stale constraint from the last plan.');
        PlanTesting::inSession(Workspace::at($this->root), $plan)->set('Stale testing choice.');
        file_put_contents(Workspace::at($this->root)->path('.plan-working-state'), "## Doing\nold plan phase 3\n");
        Counter::named(Workspace::at($this->root), 'cardinal-remind')->bump();

        $this->fire(['hook_event_name' => 'PostToolUse', 'tool_name' => 'ExitPlanMode']);

        $this->assertTrue($this->marker()->isActive(), 'the new plan is active');
        $this->assertSame([], PlanConstraints::inSession(Workspace::at($this->root), $plan)->local(), 'old constraints are wiped');
        $this->assertSame('', PlanTesting::inSession(Workspace::at($this->root), $plan)->chosen(), 'old testing choice is wiped');
        $this->assertFileDoesNotExist(Workspace::at($this->root)->path('.cardinal-remind-count'), 'counters are reset');
    }

    public function test_a_replan_preserves_working_state_to_previous_and_warns_loudly(): void
    {
        // #336: a mid-execution re-plan must NOT silently drop prior state. The working-state (the
        // compaction lifeline) is preserved as `.previous`, and the nudge warns that constraints/testing
        // were reset — so the agent re-establishes them instead of assuming carry-over.
        $plan = new \JesseGall\CodeCommandments\PlanExecution()->build();
        PlanConstraints::inSession(Workspace::at($this->root), $plan)->addLocal('No frontend logic.');
        file_put_contents(Workspace::at($this->root)->path('.plan-working-state'), "## Doing\nold plan phase 3\n");

        $context = $this->context($this->fire(['hook_event_name' => 'PostToolUse', 'tool_name' => 'ExitPlanMode']));

        $this->assertStringContainsString('RE-PLAN DETECTED', $context, 'the reset is loud, not silent');
        $this->assertStringContainsString('do NOT carry over', $context);
        $working = PlanWorkingState::inSession(Workspace::at($this->root));
        $this->assertFalse($working->exists(), 'the live working-state is reset');
        $this->assertFileExists($working->previousPath(), 'the prior working-state is preserved for reference');
    }

    public function test_a_first_plan_approval_has_no_replan_banner(): void
    {
        // No prior state → a clean plan start, no re-plan warning.
        $context = $this->context($this->fire(['hook_event_name' => 'PostToolUse', 'tool_name' => 'ExitPlanMode']));

        $this->assertStringNotContainsString('RE-PLAN DETECTED', $context);
    }

    public function test_a_post_tool_use_for_another_tool_is_ignored(): void
    {
        $this->assertSame([], $this->fire(['hook_event_name' => 'PostToolUse', 'tool_name' => 'Bash']));
        $this->assertFalse($this->marker()->isActive());
    }

    public function test_stop_is_silent_without_keep_going(): void
    {
        $this->marker()->activate('sha0'); // No config → keepGoing off.

        $this->assertSame([], $this->fire(['hook_event_name' => 'Stop']));
    }

    public function test_stop_blocks_and_continues_when_keep_going_is_on(): void
    {
        $this->writeConfig('$config->planExecution(fn ($p) => $p->keepGoing());');
        $this->marker()->activate('sha0');

        $emitted = $this->fire(['hook_event_name' => 'Stop'], head: 'sha1');

        $this->assertSame('block', $emitted[0]['decision'] ?? null);
        $this->assertStringContainsString("plan isn't finished", (string) ($emitted[0]['reason'] ?? ''));
    }

    public function test_stop_gives_up_after_the_stuck_cap_with_no_progress(): void
    {
        $this->writeConfig('$config->planExecution(fn ($p) => $p->keepGoing());');
        $this->marker()->activate('sha0');

        for ($i = 0; $i < 4; $i++) {
            $this->assertNotSame([], $this->fire(['hook_event_name' => 'Stop'], head: 'stuck'), "nudge {$i}");
        }

        $this->assertSame([], $this->fire(['hook_event_name' => 'Stop'], head: 'stuck'), 'a spinning agent is not looped forever');
    }

    public function test_progress_resets_the_stuck_counter(): void
    {
        $this->writeConfig('$config->planExecution(fn ($p) => $p->keepGoing());');
        $this->marker()->activate('sha0');

        // Every stop lands on a NEW head (a commit each phase) → never capped.
        for ($i = 0; $i < 8; $i++) {
            $this->assertNotSame([], $this->fire(['hook_event_name' => 'Stop'], head: "sha{$i}"), "progressing nudge {$i}");
        }
    }

    public function test_keep_going_self_clears_after_the_absolute_cap(): void
    {
        $this->writeConfig('$config->planExecution(fn ($p) => $p->keepGoing());');
        $this->marker()->activate('sha0');

        // Progress every stop (new head) would dodge the stuck-cap forever — the absolute total cap
        // still stops it, and clears the marker so an abandoned plan can't linger.
        for ($i = 0; $i < 40; $i++) {
            $this->fire(['hook_event_name' => 'Stop'], head: "sha{$i}");
        }

        $this->assertSame([], $this->fire(['hook_event_name' => 'Stop'], head: 'sha-final'));
        $this->assertFalse($this->marker()->isActive(), 'the stale marker is cleared for good');
    }

    public function test_respect_user_stops_nudges_only_once(): void
    {
        $this->writeConfig('$config->planExecution(fn ($p) => $p->keepGoing(\JesseGall\CodeCommandments\StopPolicy::RespectUserStops));');
        $this->marker()->activate('sha0');

        $this->assertNotSame([], $this->fire(['hook_event_name' => 'Stop'], head: 'sha1'));
        $this->assertSame([], $this->fire(['hook_event_name' => 'Stop'], head: 'sha2'), "the human's stop stands after one nudge");
    }

    public function test_relentless_never_gives_up_on_no_progress(): void
    {
        // The user's demand: never stop. Where Autonomous caps a spinning agent after the stuck-cap,
        // Relentless keeps nudging on the SAME head far past it — only the absolute total cap can end it.
        $this->writeConfig('$config->planExecution(fn ($p) => $p->mode(\JesseGall\CodeCommandments\PlanMode::Relentless));');
        $this->marker()->activate('sha0');

        for ($i = 0; $i < 20; $i++) {
            $this->assertNotSame([], $this->fire(['hook_event_name' => 'Stop'], head: 'stuck'), "relentless nudge {$i}");
        }
    }

    public function test_best_effort_never_gives_up_and_nudges_to_defer_and_retry(): void
    {
        // Between Autonomous and Relentless: never caps on no-progress (like Relentless), but the nudge is
        // completionist — defer a blocker and retry it at the end, rather than just moving on.
        $this->writeConfig('$config->planExecution(fn ($p) => $p->mode(\JesseGall\CodeCommandments\PlanMode::BestEffort));');
        $this->marker()->activate('sha0');

        $emitted = [];
        for ($i = 0; $i < 12; $i++) {
            $emitted = $this->fire(['hook_event_name' => 'Stop'], head: 'stuck');
            $this->assertNotSame([], $emitted, "best-effort nudge {$i}");
        }

        $reason = (string) ($emitted[0]['reason'] ?? '');
        $this->assertStringContainsString('BEST-EFFORT', $reason);
        $this->assertStringContainsString('DEFERRED', $reason);
        $this->assertStringContainsString('retry every deferred step', $reason);
    }

    public function test_relentless_ignores_a_stuck_signal_and_pushes_straight_back_in(): void
    {
        // No waiting in relentless: a stuck marker is cleared but the agent is nudged to SKIP and continue,
        // never granted the one-stop pause Autonomous gives.
        $this->writeConfig('$config->planExecution(fn ($p) => $p->mode(\JesseGall\CodeCommandments\PlanMode::Relentless));');
        $this->marker()->activate('sha0');
        $this->marker()->markStuck('sha0');

        $emitted = $this->fire(['hook_event_name' => 'Stop'], head: 'sha0');

        $this->assertSame('block', $emitted[0]['decision'] ?? null, 'a stuck signal does not pause a relentless run');
        $reason = (string) ($emitted[0]['reason'] ?? '');
        $this->assertStringContainsString('RELENTLESS', $reason);
        $this->assertStringContainsString('SKIP', $reason);
        $this->assertStringNotContainsString('plan stuck', $reason, 'relentless never teaches plan stuck');
    }

    public function test_stop_is_silent_while_waiting_on_a_running_background_task(): void
    {
        $this->writeConfig('$config->planExecution(fn ($p) => $p->keepGoing());');
        $this->marker()->activate('sha0');

        // The real Stop payload shape while parked on a subagent — it will auto-resume; don't nudge.
        $running = [['id' => 'a', 'type' => 'subagent', 'status' => 'running']];
        $this->assertSame(
            [],
            $this->fireWith(['hook_event_name' => 'Stop', 'background_tasks' => $running]),
            'no keep-going nudge while a background task is running',
        );
    }

    public function test_a_settled_background_task_does_not_suppress_a_real_stop(): void
    {
        $this->writeConfig('$config->planExecution(fn ($p) => $p->keepGoing());');
        $this->marker()->activate('sha0');

        // A completed task lingering in the array is NOT pending — the stop is real, so keep-going fires.
        $done = [['id' => 'a', 'type' => 'subagent', 'status' => 'completed']];
        $emitted = $this->fireWith(['hook_event_name' => 'Stop', 'background_tasks' => $done], 'sha1');

        $this->assertSame('block', $emitted[0]['decision'] ?? null, 'a settled task still lets the plan nudge');
    }

    /**
     * Fire with a full payload (not just an event name), returning what was emitted.
     *
     * @param  array<string, mixed>  $payload
     * @return list<array<string, mixed>>
     */
    private function fireWith(array $payload, string $head = 'sha1'): array
    {
        $io = new CapturingHookIO(new FakeGit($this->root, $head, 'plan/x'), $payload);
        new PlanReminder($io)->run([]);

        return $io->emitted;
    }

    public function test_stuck_is_one_shot_suppresses_one_stop_then_clears(): void
    {
        $this->writeConfig('$config->planExecution(fn ($p) => $p->keepGoing());');
        $this->marker()->activate('sha0');
        $this->marker()->markStuck('sha0');

        // The stop right after `plan stuck` is silent (don't loop a blocked agent) AND clears the signal,
        // so the plan stays active but keep-going isn't disabled for the rest of the run.
        $this->assertSame([], $this->fire(['hook_event_name' => 'Stop'], head: 'sha0'));
        $this->assertTrue($this->marker()->isActive(), 'the plan stays active');
        $this->assertNull($this->marker()->stuckAt(), 'the stuck signal is one-shot — removed as the agent continues');
    }

    public function test_keep_going_resumes_on_the_next_stop_after_stuck(): void
    {
        $this->writeConfig('$config->planExecution(fn ($p) => $p->keepGoing());');
        $this->marker()->activate('sha0');
        $this->marker()->markStuck('sha0');

        $this->fire(['hook_event_name' => 'Stop'], head: 'sha0'); // the one suppressed stop
        // The agent continued (no commit needed) — the next stop gets a normal keep-going nudge.
        $emitted = $this->fire(['hook_event_name' => 'Stop'], head: 'sha0');

        $this->assertSame('block', $emitted[0]['decision'] ?? null, 'keep-going resumes once the agent continues');
    }

    public function test_stop_clears_the_marker_once_back_on_the_base_branch(): void
    {
        $this->writeConfig('$config->planExecution(fn ($p) => $p->keepGoing());');
        $this->marker()->activate('sha0');

        $this->assertSame([], $this->fire(['hook_event_name' => 'Stop'], branch: 'main'));
        $this->assertFalse($this->marker()->isActive(), 'merged back to base — plan is over');
    }

    public function test_the_base_branch_is_read_live_from_config_not_snapshotted(): void
    {
        // The marker stores NO base. The "back on base ⇒ clear" check uses the base from config at
        // firing time — so a base edited mid-plan (here `develop`) is honoured immediately.
        $this->writeConfig('$config->planExecution(fn ($p) => $p->branchFrom(\'develop\')->keepGoing());');
        $this->marker()->activate('sha0');

        $this->assertSame([], $this->fire(['hook_event_name' => 'Stop'], branch: 'develop'));
        $this->assertFalse($this->marker()->isActive(), 'cleared on the live base branch');
    }

    /**
     * Fire the hook with $payload; return the list of emitted response payloads.
     *
     * @param  array<string, mixed>  $payload
     * @return list<array<string, mixed>>
     */
    private function fire(array $payload, string $head = 'sha', string $branch = 'plan/x'): array
    {
        $io = new CapturingHookIO(new FakeGit($this->root, $head, $branch), $payload);
        new PlanReminder($io)->run([]);

        return $io->emitted;
    }

    /**
     * @param  list<array<string, mixed>>  $emitted
     */
    private function context(array $emitted): string
    {
        return (string) ($emitted[0]['hookSpecificOutput']['additionalContext'] ?? '');
    }

    private function marker(): PlanMarker
    {
        return PlanMarker::inSession(Workspace::at($this->root));
    }

    private function writeConfig(string $body): void
    {
        file_put_contents(
            $this->root . '/.commandments/config.php',
            "<?php\nuse JesseGall\\CodeCommandments\\Config;\nreturn function (Config \$config): void {\n    {$body}\n};\n",
        );
    }
}
