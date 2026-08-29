<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Cli\Orchestration;

use JesseGall\CodeCommandments\Hooks\Handlers\MergeGate;
use JesseGall\CodeCommandments\Hooks\HookResponse;
use JesseGall\CodeCommandments\Hooks\RecordingHookIO;
use JesseGall\CodeCommandments\Tests\Cli\FakeGit;
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

    private function refusalFor(string $command, string $agentType = ''): string
    {
        $payload = [
            'hook_event_name' => 'PreToolUse',
            'tool_name' => 'Bash',
            'tool_input' => ['command' => $command],
        ];

        if ($agentType !== '') {
            $payload['agent_type'] = $agentType;
            $payload['agent_id'] = 'a123';
        }

        $io = new RecordingHookIO($payload, new FakeGit($this->repo));
        $was = getcwd();
        chdir($this->repo);

        new MergeGate($io)->run([]);

        chdir((string) $was);

        return implode("\n", array_map(fn (HookResponse $r) => $r->blockReason->unwrapOr(''), $io->emitted));
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
}
