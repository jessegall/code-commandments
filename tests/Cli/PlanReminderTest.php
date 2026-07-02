<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Cli;

use JesseGall\CodeCommandments\Cli\PlanMarker;
use JesseGall\CodeCommandments\Cli\PlanReminder;
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
        @unlink($this->root . '/.commandments/.plan-active');
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

    public function test_a_post_tool_use_for_another_tool_is_ignored(): void
    {
        $this->assertSame([], $this->fire(['hook_event_name' => 'PostToolUse', 'tool_name' => 'Bash']));
        $this->assertFalse($this->marker()->isActive());
    }

    public function test_stop_is_silent_without_keep_going(): void
    {
        $this->marker()->activate('main', 'sha0'); // No config → keepGoing off.

        $this->assertSame([], $this->fire(['hook_event_name' => 'Stop']));
    }

    public function test_stop_blocks_and_continues_when_keep_going_is_on(): void
    {
        $this->writeConfig('$config->planExecution(fn ($p) => $p->keepGoing());');
        $this->marker()->activate('main', 'sha0');

        $emitted = $this->fire(['hook_event_name' => 'Stop'], head: 'sha1');

        $this->assertSame('block', $emitted[0]['decision'] ?? null);
        $this->assertStringContainsString("plan isn't finished", (string) ($emitted[0]['reason'] ?? ''));
    }

    public function test_stop_gives_up_after_the_stuck_cap_with_no_progress(): void
    {
        $this->writeConfig('$config->planExecution(fn ($p) => $p->keepGoing());');
        $this->marker()->activate('main', 'sha0');

        for ($i = 0; $i < 4; $i++) {
            $this->assertNotSame([], $this->fire(['hook_event_name' => 'Stop'], head: 'stuck'), "nudge {$i}");
        }

        $this->assertSame([], $this->fire(['hook_event_name' => 'Stop'], head: 'stuck'), 'a spinning agent is not looped forever');
    }

    public function test_progress_resets_the_stuck_counter(): void
    {
        $this->writeConfig('$config->planExecution(fn ($p) => $p->keepGoing());');
        $this->marker()->activate('main', 'sha0');

        // Every stop lands on a NEW head (a commit each phase) → never capped.
        for ($i = 0; $i < 8; $i++) {
            $this->assertNotSame([], $this->fire(['hook_event_name' => 'Stop'], head: "sha{$i}"), "progressing nudge {$i}");
        }
    }

    public function test_keep_going_self_clears_after_the_absolute_cap(): void
    {
        $this->writeConfig('$config->planExecution(fn ($p) => $p->keepGoing());');
        $this->marker()->activate('main', 'sha0');

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
        $this->marker()->activate('main', 'sha0');

        $this->assertNotSame([], $this->fire(['hook_event_name' => 'Stop'], head: 'sha1'));
        $this->assertSame([], $this->fire(['hook_event_name' => 'Stop'], head: 'sha2'), "the human's stop stands after one nudge");
    }

    public function test_stop_clears_the_marker_once_back_on_the_base_branch(): void
    {
        $this->writeConfig('$config->planExecution(fn ($p) => $p->keepGoing());');
        $this->marker()->activate('main', 'sha0');

        $this->assertSame([], $this->fire(['hook_event_name' => 'Stop'], branch: 'main'));
        $this->assertFalse($this->marker()->isActive(), 'merged back to base — plan is over');
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
        return PlanMarker::inWorktree($this->root);
    }

    private function writeConfig(string $body): void
    {
        file_put_contents(
            $this->root . '/.commandments/config.php',
            "<?php\nuse JesseGall\\CodeCommandments\\Config;\nreturn function (Config \$config): void {\n    {$body}\n};\n",
        );
    }
}
