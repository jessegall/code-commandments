<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Cli;

use JesseGall\CodeCommandments\Cli\HookDispatch;
use JesseGall\CodeCommandments\Cli\Input;
use JesseGall\CodeCommandments\Cli\PlanMarker;
use PHPUnit\Framework\TestCase;

/**
 * The one entry point every wired moment runs through: it fans out to the whole registry, merges what
 * the handlers emit, and stays silent when nothing applies. Driven through a {@see CapturingHookIO} +
 * {@see FakeGit}, so no STDIN, harness, or real repository.
 */
final class HookDispatchTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/cc-dispatch-' . uniqid('', true);
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
        PlanMarker::inWorktree($this->root)->activate('sha1');

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
        PlanMarker::inWorktree($this->root)->activate('sha0');

        $emitted = $this->dispatch(['hook_event_name' => 'Stop']);

        $this->assertSame('block', $emitted[0]['decision'] ?? null);
        $this->assertStringContainsString("plan isn't finished", (string) ($emitted[0]['reason'] ?? ''));
    }

    public function test_stop_is_silent_while_parked_on_background_work(): void
    {
        $this->writeConfig('$config->planExecution(fn ($p) => $p->keepGoing());');
        PlanMarker::inWorktree($this->root)->activate('sha0');

        $emitted = $this->dispatch([
            'hook_event_name' => 'Stop',
            'background_tasks' => [['id' => 'a', 'status' => 'running']],
        ]);

        $this->assertSame([], $emitted, 'the base-class guard suppresses every Stop handler');
    }
}
