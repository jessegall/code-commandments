<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Cli\Orchestration;

use JesseGall\CodeCommandments\Hooks\Handlers\MergeGate;
use JesseGall\CodeCommandments\Hooks\HookResponse;
use JesseGall\CodeCommandments\Hooks\RecordingHookIO;
use JesseGall\CodeCommandments\Cli\Orchestration\Roles;
use JesseGall\CodeCommandments\Tests\Cli\FakeGit;
use JesseGall\CodeCommandments\Workspace;
use PHPUnit\Framework\TestCase;

/**
 * One writer is what keeps a shared branch a place work ARRIVES rather than a place several agents race.
 * Nothing enforced it in the build that paid for this — it held because the orchestrator re-stated it in
 * every brief and did not slip.
 */
final class MergeGateTest extends TestCase
{
    private string $repo;

    protected function setUp(): void
    {
        $this->repo = sys_get_temp_dir() . '/cc-mergegate-' . uniqid('', true);
        mkdir($this->repo . '/.commandments', 0777, true);

        exec('cd ' . escapeshellarg($this->repo) . ' && git init -q -b to-vue && git commit -q --allow-empty -m first 2>/dev/null');
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->repo));
    }

    private function declare(string $body): void
    {
        file_put_contents($this->repo . '/.commandments/config.php', <<<PHP
        <?php
        return function (\\JesseGall\\CodeCommandments\\Config \$config): void {
            {$body}
        };
        PHP);
    }

    private function refusalFor(string $command, string $agentType = '', string $agentId = 'a123', string $cwd = ''): string
    {
        $payload = [
            'hook_event_name' => 'PreToolUse',
            'session_id' => 'sess-merge',
            'tool_name' => 'Bash',
            'tool_input' => ['command' => $command],
            'cwd' => $cwd === '' ? $this->repo : $cwd,
        ];

        if ($agentType !== '') {
            $payload['agent_type'] = $agentType;
            $payload['agent_id'] = $agentId;
        }

        $io = new RecordingHookIO($payload, new FakeGit($this->repo));
        $was = getcwd();
        chdir($this->repo);

        new MergeGate($io)->run([]);

        chdir((string) $was);

        return implode("\n", array_map(fn (HookResponse $r) => $r->blockReason->unwrapOr(''), $io->emitted));
    }

    /**
     * Every agent in a real build may share one type, because a type is fixed at spawn and roles are
     * declared later. A rule that read "not the writer" from an actor it cannot name would refuse the
     * WRITER too, and stop the build on the day the config was turned on.
     */
    public function test_an_actor_it_cannot_name_is_not_refused(): void
    {
        $this->declare("\$config->orchestration(fn (\$o) => \$o->branch('to-vue')->writtenBy('integrator'));");

        $this->assertSame('', $this->refusalFor('git merge lane/styling'), 'no role, no refusal');
    }

    /**
     * But it must not go quiet either — a rule doing nothing in silence is how an outage hides.
     */
    public function test_it_says_when_it_cannot_tell(): void
    {
        $this->declare("\$config->orchestration(fn (\$o) => \$o->branch('to-vue')->writtenBy('integrator'));");

        $io = new RecordingHookIO([
            'hook_event_name' => 'PreToolUse',
            'session_id' => 'sess-merge',
            'tool_name' => 'Bash',
            'tool_input' => ['command' => 'git merge lane/styling'],
        ], new FakeGit($this->repo));

        $was = getcwd();
        chdir($this->repo);
        new MergeGate($io)->run([]);
        chdir((string) $was);

        $said = implode("\n", array_map(fn (HookResponse $r) => $r->context->unwrapOr(''), $io->emitted));

        $this->assertStringContainsString('NOT enforcing', $said);
        $this->assertStringContainsString('build assign', $said, 'it names the way out');
    }

    /**
     * An agent already alive cannot change its type, and the agents worth a role are the ones a respawn
     * would ruin — so a role can be pointed at a live agent by its id, and that wins over its type.
     */
    public function test_an_assigned_role_lets_a_live_agent_merge(): void
    {
        $this->declare("\$config->orchestration(fn (\$o) => \$o->branch('to-vue')->writtenBy('integrator'));");

        $this->assertStringContainsString(
            'only `integrator` merges',
            $this->refusalFor('git merge lane/styling', 'general-purpose', 'agent-77'),
        );

        Roles::inSession(Workspace::at($this->repo, 'sess-merge'))->assign('agent-77', 'integrator');

        $this->assertSame('', $this->refusalFor('git merge lane/styling', 'general-purpose', 'agent-77'));
    }

    public function test_a_merge_into_the_shared_branch_by_anyone_else_is_refused(): void
    {
        $this->declare("\$config->orchestration(fn (\$o) => \$o->branch('to-vue')->writtenBy('integrator'));");

        $refusal = $this->refusalFor('git merge lane/styling', 'builder');

        $this->assertStringContainsString('only `integrator` merges', $refusal);
        $this->assertStringContainsString('committed sha', $refusal, 'it says how to hand the work over');
    }

    public function test_the_writer_may_merge(): void
    {
        $this->declare("\$config->orchestration(fn (\$o) => \$o->branch('to-vue')->writtenBy('integrator'));");

        $this->assertSame('', $this->refusalFor('git merge lane/styling', 'integrator'));
    }

    /**
     * An undeclared rule refuses nobody — a project that never named a branch is not running this.
     */
    public function test_nothing_declared_refuses_nothing(): void
    {
        $this->declare('// nothing declared');

        $this->assertSame('', $this->refusalFor('git merge lane/styling', 'builder'));
    }

    /**
     * A branch was named but no writer, so there is no rule about who may merge yet.
     */
    public function test_a_branch_without_a_writer_refuses_nobody(): void
    {
        $this->declare("\$config->orchestration(fn (\$o) => \$o->branch('to-vue'));");

        $this->assertSame('', $this->refusalFor('git merge lane/styling', 'builder'));
    }

    /**
     * Text about a merge is not a merge — the failure that shipped once already on the sibling rule.
     */
    public function test_text_mentioning_a_merge_is_not_a_merge(): void
    {
        $this->declare("\$config->orchestration(fn (\$o) => \$o->branch('to-vue')->writtenBy('integrator'));");

        $this->assertSame('', $this->refusalFor('echo "never run git merge here"', 'builder'));
        $this->assertSame('', $this->refusalFor("grep -rn 'git merge' docs/", 'builder'));
    }

    /**
     * A lane pulling the shared branch INTO itself, with the hook's own process standing in the root
     * checkout — which is on the shared branch. Resolving HEAD from the process rather than from the
     * worktree the merge runs in made this read as a merge INTO the protected branch.
     *
     * It is also the direction that can never be what a writer rule protects: nothing is written to the
     * protected branch at all. And the refusal was correct-LOOKING, so the natural reading was "the gate
     * works" and the natural fix was to route around it.
     */
    public function test_a_lane_merging_the_shared_branch_into_itself_is_not_refused(): void
    {
        $this->declare("\$config->orchestration(fn (\$o) => \$o->branch('to-vue')->writtenBy('integrator'));");

        $lane = $this->repo . '-lane';
        exec('git -C ' . escapeshellarg($this->repo) . ' worktree add -q -b lane/editor ' . escapeshellarg($lane) . ' 2>/dev/null');

        $this->assertSame(
            '',
            $this->refusalFor('git merge to-vue', 'builder', 'a999', $lane),
            'the shared branch coming INTO a lane writes nothing to the shared branch',
        );

        exec('git -C ' . escapeshellarg($this->repo) . ' worktree remove --force ' . escapeshellarg($lane) . ' 2>/dev/null');
    }

    /**
     * THE SHAPE THAT WAS REPORTED FOUR TIMES. A subagent's shell starts in the repository root whatever
     * worktree its work is in, so it reaches its lane with a leading `cd` inside the one command. The
     * gate asked the hook's own process where it was standing, got the root — which is on the shared
     * branch — and refused the bootstrap every builder must run.
     */
    public function test_a_lane_reached_by_cd_inside_the_command_is_not_refused(): void
    {
        $this->declare("\$config->orchestration(fn (\$o) => \$o->branch('to-vue')->writtenBy('integrator'));");

        $lane = $this->repo . '-cd-lane';
        exec('git -C ' . escapeshellarg($this->repo) . ' worktree add -q -b lane/cd ' . escapeshellarg($lane) . ' 2>/dev/null');

        $refusal = $this->refusalFor('cd ' . $lane . ' && git merge to-vue', 'builder', 'a999', $this->repo);

        exec('git -C ' . escapeshellarg($this->repo) . ' worktree remove --force ' . escapeshellarg($lane) . ' 2>/dev/null');

        $this->assertSame('', $refusal, 'the merge runs in the lane, not in the directory the hook stands in');
    }

    /**
     * `git -C <dir>` names a worktree for one invocation without moving anything, and merging by sha
     * rather than by name does not change where the merge LANDS — which is the whole question. The
     * spelling that defeated the earlier rule was `git merge $(git rev-parse origin/to-vue)`: it reads
     * the destination from the argv, and the destination was never in the argv.
     */
    public function test_a_merge_is_judged_by_the_worktree_git_is_pointed_at(): void
    {
        $this->declare("\$config->orchestration(fn (\$o) => \$o->branch('to-vue')->writtenBy('integrator'));");

        $lane = $this->repo . '-dashc-lane';
        exec('git -C ' . escapeshellarg($this->repo) . ' worktree add -q -b lane/dashc ' . escapeshellarg($lane) . ' 2>/dev/null');

        $intoLane = $this->refusalFor('git -C ' . $lane . ' merge to-vue', 'builder', 'a999', $this->repo);
        $intoBranch = $this->refusalFor('git -C ' . $this->repo . ' merge lane/dashc', 'builder', 'a999', $lane);

        exec('git -C ' . escapeshellarg($this->repo) . ' worktree remove --force ' . escapeshellarg($lane) . ' 2>/dev/null');

        $this->assertSame('', $intoLane, 'pointed at the lane, so nothing reaches the shared branch');
        $this->assertStringContainsString('only `integrator` merges', $intoBranch, 'pointed at the root, which is on to-vue');
    }

    /**
     * A commit message is prose about the work, and the work is often the very rule the gate enforces.
     * Blanking only quoted strings left a heredoc body reading as commands, so a message describing a
     * merge was refused as one.
     */
    public function test_a_merge_described_in_a_heredoc_is_not_a_merge(): void
    {
        $this->declare("\$config->orchestration(fn (\$o) => \$o->branch('to-vue')->writtenBy('integrator'));");

        $message = "git commit -F - <<'EOF'\nfix: the gate\n\ngit merge to-vue was refused in a lane\nEOF";

        $this->assertSame('', $this->refusalFor($message, 'builder'));
    }

    /**
     * The other direction from the same lane still is the rule's business: standing on the protected
     * branch is what the gate guards, wherever the hook's own process happens to be.
     */
    public function test_a_merge_into_the_shared_branch_is_still_refused_from_another_directory(): void
    {
        $this->declare("\$config->orchestration(fn (\$o) => \$o->branch('to-vue')->writtenBy('integrator'));");

        $elsewhere = sys_get_temp_dir() . '/cc-mergegate-elsewhere-' . uniqid('', true);
        mkdir($elsewhere, 0777, true);

        $refusal = $this->refusalFor('git merge lane/editor', 'builder', 'a999', $this->repo);

        rmdir($elsewhere);

        $this->assertStringContainsString('to-vue', $refusal, 'the repo is on to-vue, so this is the guarded direction');
    }
}
