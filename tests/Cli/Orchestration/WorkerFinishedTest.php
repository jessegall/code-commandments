<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Cli\Orchestration;

use JesseGall\CodeCommandments\Cli\Orchestration\Events\WorkerFinished;
use JesseGall\CodeCommandments\Cli\Orchestration\Instance;
use JesseGall\CodeCommandments\Hooks\Handlers\WorkerFinishedTrigger;
use JesseGall\CodeCommandments\Hooks\RecordingHookIO;
use JesseGall\CodeCommandments\Tests\Cli\FakeGit;
use JesseGall\CodeCommandments\Agent;
use JesseGall\CodeCommandments\Workspace;
use PHPUnit\Framework\TestCase;

/**
 * The moment the layer was missing. Every other moment fires from a CLI command somebody had to remember
 * to run — which is the same failure as the filing it was meant to trigger. `SubagentStop` is the harness
 * saying a worker stopped, and it is the only completion signal that is a measurement rather than an
 * inference from silence.
 */
final class WorkerFinishedTest extends TestCase
{
    private string $root;

    private string|false $priorProjectDir;

    private string|false $priorSession;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/cc-finished-' . uniqid('', true);
        mkdir($this->root . '/.commandments/orchestrator/profiles/demo/triggers', 0777, true);
        file_put_contents($this->root . '/.commandments/orchestrator/profiles/demo/profile.md', '# demo');

        $this->priorProjectDir = getenv('CLAUDE_PROJECT_DIR');
        $this->priorSession = getenv('CLAUDE_CODE_SESSION_ID');
        putenv('CLAUDE_PROJECT_DIR=' . $this->root);
        putenv('CLAUDE_CODE_SESSION_ID=finished-test');
    }

    protected function tearDown(): void
    {
        putenv($this->priorProjectDir === false ? 'CLAUDE_PROJECT_DIR' : 'CLAUDE_PROJECT_DIR=' . $this->priorProjectDir);
        putenv($this->priorSession === false ? 'CLAUDE_CODE_SESSION_ID' : 'CLAUDE_CODE_SESSION_ID=' . $this->priorSession);
        exec('rm -rf ' . escapeshellarg($this->root));
    }

    private function orchestrating(): void
    {
        Instance::inSession(Workspace::ofSession($this->root))->start('demo', '10:00');
    }

    private function armTrigger(string $says): void
    {
        file_put_contents($this->root . '/.commandments/orchestrator/profiles/demo/triggers/Filer.php', <<<PHP
            <?php

            declare(strict_types=1);

            namespace Demo\\Triggers;

            use JesseGall\\CodeCommandments\\Cli\\Orchestration\\Events\\Trigger;
            use JesseGall\\CodeCommandments\\Cli\\Orchestration\\Events\\WorkerFinished;
            use JesseGall\\CodeCommandments\\Cli\\Orchestration\\Events\\Verdict;

            final class Filer extends Trigger
            {
                public function fire(WorkerFinished \$moment): Verdict
                {
                    return Verdict::note("{$says} " . \$moment->agent->type);
                }
            }
            PHP);
    }

    /**
     * @return list<string>
     */
    private function stopped(string $agent = 'a123', string $type = 'walker'): array
    {
        $io = new RecordingHookIO([
            'hook_event_name' => 'SubagentStop',
            'session_id' => 'finished-test',
            'agent_id' => $agent,
            'agent_type' => $type,
        ], new FakeGit($this->root));

        new WorkerFinishedTrigger($io)->run([]);

        return array_map(fn ($response) => $response->context->unwrapOr(''), $io->emitted);
    }

    public function test_a_worker_stopping_reaches_the_builds_own_trigger(): void
    {
        $this->orchestrating();
        $this->armTrigger('dispatch the secretary for');

        $this->assertStringContainsString('dispatch the secretary for walker', implode("\n", $this->stopped()));
    }

    /**
     * A trigger belongs to the profile it serves. A session not orchestrating loads none, so the question
     * of whether a build's rule should fire there never arises.
     */
    public function test_a_session_not_orchestrating_arms_nothing(): void
    {
        $this->armTrigger('dispatch the secretary for');

        $this->assertSame([], $this->stopped());
    }

    public function test_a_profile_with_no_triggers_says_nothing(): void
    {
        $this->orchestrating();

        $this->assertSame([], $this->stopped());
    }

    /**
     * The moment carries the AGENT and nothing about the board, because a worker can finish having never
     * claimed an item — its findings existing only in a message.
     */
    public function test_the_moment_names_the_agent_without_touching_the_board(): void
    {
        $moment = new WorkerFinished('/repo', new Agent('a999', 'walker'));

        $this->assertSame('a999', $moment->agent->id);
        $this->assertSame('walker', $moment->agent->type);
        $this->assertStringContainsString('walker', $moment->label());
    }

    public function test_an_agent_with_no_type_is_named_by_its_id(): void
    {
        $this->assertStringContainsString('a999', new WorkerFinished('/repo', new Agent('a999', ''))->label());
    }
}
