<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Cli;

use JesseGall\CodeCommandments\Hooks\Handlers\JudgeReminder;
use JesseGall\CodeCommandments\Hooks\HookEvent;
use JesseGall\CodeCommandments\Workspace;
use PHPUnit\Framework\TestCase;

/**
 * The nudge fires ONCE per batch (keyed on the changed-file set) when judged files are touched, then
 * stays silent — across a commit too — and says nothing in a clean tree or outside a repo. The
 * PreToolUse path only speaks for a real `git commit`. Proven against a real temp git repo, since the
 * whole point is its reading of git state.
 */
final class JudgeReminderTest extends TestCase
{
    private string $repo;

    protected function setUp(): void
    {
        $this->repo = sys_get_temp_dir() . '/cc-judge-' . uniqid('', true);
        mkdir($this->repo);
        $this->git('init -q');
        $this->git('config user.email a@b.c');
        $this->git('config user.name test');
        $this->commit('README.md', '# repo'); // a HEAD to diff against
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->repo));
    }

    public function test_it_stays_silent_on_a_clean_tree(): void
    {
        $this->assertNull((new JudgeReminder)->reminder(new HookEvent([], $this->repo)));
    }

    public function test_it_nudges_once_for_a_touched_judged_file_then_goes_silent(): void
    {
        file_put_contents($this->repo . '/Service.php', "<?php\n");

        $first = (new JudgeReminder)->reminder(new HookEvent([], $this->repo));
        $this->assertNotNull($first, 'a touched .php file earns a nudge');
        $this->assertStringContainsString('judge', $first);

        $this->assertNull((new JudgeReminder)->reminder(new HookEvent([], $this->repo)), 'the same batch is silent after one nudge');
    }

    public function test_it_nudges_to_finish_an_open_worklist_once_per_state(): void
    {
        mkdir(Workspace::at($this->repo)->sessionDir(), 0777, true);
        $this->writeChecklist(['app/A.php:5  A::m  [X]', 'app/B.php:9  B::n  [Y]']);

        $first = (new JudgeReminder)->reminder(new HookEvent([], $this->repo));
        $this->assertNotNull($first, 'an open worklist earns a nudge');
        $this->assertStringContainsString('OPEN worklist', $first);
        $this->assertStringContainsString('2 sins', $first);

        $this->assertNull((new JudgeReminder)->reminder(new HookEvent([], $this->repo)), 'the same unchanged worklist is not re-nudged');

        // Working a line off re-arms the nudge — "keep going, 1 left".
        $this->writeChecklist(['app/A.php:5  A::m  [X]']);
        $again = (new JudgeReminder)->reminder(new HookEvent([], $this->repo));
        $this->assertNotNull($again, 'a worked-off line re-arms the nudge');
        $this->assertStringContainsString('1 sin', $again);
    }

    public function test_an_empty_worklist_does_not_nudge(): void
    {
        mkdir(Workspace::at($this->repo)->sessionDir(), 0777, true);
        file_put_contents(Workspace::at($this->repo)->path('sins.md'), "# Code Commandments\n\nAll clear.\n");

        $this->assertNull((new JudgeReminder)->reminder(new HookEvent([], $this->repo)), 'a worklist with no sin lines is done');
    }

    private function writeChecklist(array $sinLines): void
    {
        $body = "# Code Commandments — sins to fix\n\n## backend/absence\n\n";

        foreach ($sinLines as $line) {
            $body .= "- `{$line}`\n";
        }

        file_put_contents(Workspace::at($this->repo)->path('sins.md'), $body);
    }

    public function test_it_stays_silent_while_a_plan_is_active(): void
    {
        file_put_contents($this->repo . '/Service.php', "<?php\n");

        // A plan judges once at the end (checks complete), committing each phase unjudged — so no nudge.
        \JesseGall\CodeCommandments\Cli\Plan\PlanMarker::inSession(\JesseGall\CodeCommandments\Workspace::at($this->repo))->activate('HEAD');
        $this->assertNull((new JudgeReminder)->reminder(new HookEvent([], $this->repo)), 'silent while a plan runs');

        // Once the plan is done, the nudge resumes.
        \JesseGall\CodeCommandments\Cli\Plan\PlanMarker::inSession(\JesseGall\CodeCommandments\Workspace::at($this->repo))->clear();
        $this->assertNotNull((new JudgeReminder)->reminder(new HookEvent([], $this->repo)), 'nudges again after the plan ends');
    }

    public function test_it_ignores_non_judged_files(): void
    {
        file_put_contents($this->repo . '/notes.txt', 'hi');

        $this->assertNull((new JudgeReminder)->reminder(new HookEvent([], $this->repo)));
    }

    public function test_committing_the_same_files_stays_silent_no_double_nudge(): void
    {
        file_put_contents($this->repo . '/Service.php', "<?php\n");
        $this->assertNotNull((new JudgeReminder)->reminder(new HookEvent([], $this->repo)), 'first nudge');

        // Commit the very files we nudged for. The SET is unchanged, so — even though HEAD moves and
        // the finding now shows as committed branch work — there is nothing new: stay silent.
        $this->git('add -A');
        $this->git('commit -q -m work');

        $this->assertNull((new JudgeReminder)->reminder(new HookEvent([], $this->repo)), 'no second nudge for the same set across a commit');
    }

    public function test_touching_a_new_file_earns_a_fresh_nudge(): void
    {
        file_put_contents($this->repo . '/Service.php', "<?php\n");
        $this->assertNotNull((new JudgeReminder)->reminder(new HookEvent([], $this->repo)));
        $this->assertNull((new JudgeReminder)->reminder(new HookEvent([], $this->repo)), 'silent for the same set');

        file_put_contents($this->repo . '/Other.vue', "<template></template>\n");
        $this->assertNotNull((new JudgeReminder)->reminder(new HookEvent([], $this->repo)), 'a NEW file grows the set — nudge again');
    }

    public function test_a_clean_tree_clears_the_marker_so_the_next_batch_starts_over(): void
    {
        file_put_contents($this->repo . '/Service.php', "<?php\n");
        $this->assertNotNull((new JudgeReminder)->reminder(new HookEvent([], $this->repo)));

        unlink($this->repo . '/Service.php'); // tree clean again
        $this->assertNull((new JudgeReminder)->reminder(new HookEvent([], $this->repo)), 'silent on a clean tree');

        file_put_contents($this->repo . '/Service.php', "<?php\n"); // same path, fresh batch
        $this->assertNotNull((new JudgeReminder)->reminder(new HookEvent([], $this->repo)), 'the cleared marker lets the same path nudge again');
    }

    public function test_it_is_silent_outside_a_repository(): void
    {
        $bare = sys_get_temp_dir() . '/cc-nonrepo-' . uniqid('', true);
        mkdir($bare);

        $this->assertNull((new JudgeReminder)->reminder(new HookEvent([], $bare)));

        rmdir($bare);
    }

    public function test_a_clean_worktree_ignores_the_main_checkouts_changes(): void
    {
        // A dirty MAIN checkout: an unjudged change lives there, not in the worktree.
        file_put_contents($this->repo . '/Service.php', "<?php\n");

        // A FRESH worktree off HEAD — clean, nothing touched in it.
        $worktree = sys_get_temp_dir() . '/cc-worktree-' . uniqid('', true);
        $this->git('worktree add -q -b feature ' . escapeshellarg($worktree) . ' HEAD');

        // The hook runs IN the worktree, but CLAUDE_PROJECT_DIR stays pinned to the main
        // checkout. It must scope to the worktree (clean → silent), not the main dir.
        $out = $this->runCliIn($worktree, ['CLAUDE_PROJECT_DIR' => $this->repo], ['hook_event_name' => 'Stop']);

        $this->assertSame('', trim($out), 'a clean worktree must not nudge about the main checkout');

        $this->git('worktree remove --force ' . escapeshellarg($worktree));
    }

    public function test_pre_tool_use_injects_context_for_a_git_commit_but_ignores_other_bash(): void
    {
        file_put_contents($this->repo . '/Service.php', "<?php\n");

        $commit = $this->runCli(['hook_event_name' => 'PreToolUse', 'tool_name' => 'Bash', 'tool_input' => ['command' => 'git commit -m wip']]);
        $this->assertStringContainsString('additionalContext', $commit, 'a git commit gets a PreToolUse nudge');
        $this->assertStringContainsString('before you commit', $commit);

        // A fresh set (delete the marker the commit run wrote) so suppression can't mask the result.
        @unlink(Workspace::at($this->repo)->path('.judge-reminded'));

        $other = $this->runCli(['hook_event_name' => 'PreToolUse', 'tool_name' => 'Bash', 'tool_input' => ['command' => 'php artisan test']]);
        $this->assertSame('', trim($other), 'a non-commit Bash call is ignored');
    }

    public function test_stop_is_silent_while_waiting_on_a_background_task(): void
    {
        file_put_contents($this->repo . '/Service.php', "<?php\n"); // a touched judged file — would normally nudge

        // Parked waiting on a background task: silent, and it must NOT consume the batch.
        $waiting = $this->runCli(['hook_event_name' => 'Stop', 'background_tasks' => [['id' => 'a']]]);
        $this->assertSame('', trim($waiting), 'no nudge while parked on background work');

        // The same stop once genuinely done still nudges — proving the file was never marked reminded.
        $done = $this->runCli(['hook_event_name' => 'Stop']);
        $this->assertStringContainsString('block', $done, 'a real stop with the same dirty file still nudges');
    }

    /** @param array<string, mixed> $payload */
    private function runCli(array $payload): string
    {
        return $this->runCliIn($this->repo, ['CLAUDE_PROJECT_DIR' => $this->repo], $payload);
    }

    /**
     * Run the hook CLI with an explicit working directory and environment — so a test can
     * drive it from a worktree while `CLAUDE_PROJECT_DIR` points at the main checkout.
     *
     * @param  array<string, string>  $env
     * @param  array<string, mixed>  $payload
     */
    private function runCliIn(string $cwd, array $env, array $payload): string
    {
        $bin = dirname(__DIR__, 2) . '/bin/commandments';
        $descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];

        $process = proc_open('php ' . escapeshellarg($bin) . ' judge-reminder', $descriptors, $pipes, $cwd, [...$env, 'PATH' => getenv('PATH')]);
        fwrite($pipes[0], json_encode($payload));
        fclose($pipes[0]);
        $out = (string) stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);

        return $out;
    }

    private function commit(string $file, string $content): void
    {
        file_put_contents($this->repo . '/' . $file, $content);
        $this->git('add -A');
        $this->git('commit -q -m init');
    }

    private function git(string $args): void
    {
        exec('git -C ' . escapeshellarg($this->repo) . ' ' . $args . ' 2>/dev/null');
    }
}
