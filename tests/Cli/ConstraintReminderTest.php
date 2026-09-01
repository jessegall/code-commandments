<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Cli;

use JesseGall\CodeCommandments\Hooks\Handlers\ConstraintReminder;
use JesseGall\CodeCommandments\Cli\Plan\PlanMarker;
use JesseGall\CodeCommandments\Workspace;
use PHPUnit\Framework\TestCase;

/**
 * The constraint recall: while a plan is active and constraints are in force, a compaction or resume
 * re-surfaces them — and nothing else does, least of all a tool use that broke no rule. Driven through
 * a {@see CapturingHookIO} + {@see FakeGit}.
 */
final class ConstraintReminderTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/cc-creminder-' . uniqid('', true);
        mkdir($this->root . '/.commandments', 0777, true);
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->root));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fire(array $payload = ['hook_event_name' => 'SessionStart', 'source' => 'compact']): array
    {
        $io = new CapturingHookIO(new FakeGit($this->root), $payload);
        new ConstraintReminder($io)->run([]);

        return $io->emitted;
    }

    private function context(array $emitted): string
    {
        return $emitted[0]->context->unwrapOr('');
    }

    private function writeConstraint(): void
    {
        file_put_contents(
            $this->root . '/.commandments/config.php',
            "<?php\nuse JesseGall\\CodeCommandments\\Config;\nreturn function (Config \$config): void {\n"
            . "    \$config->planExecution(fn (\$p) => \$p->constraint('No frontend logic.'));\n};\n",
        );
    }

    public function test_surfaces_the_constraints_when_a_compaction_continues_the_plan(): void
    {
        $this->writeConstraint();
        PlanMarker::inSession(Workspace::at($this->root))->activate('sha');

        $context = $this->context($this->fire());
        $this->assertStringContainsString('No frontend logic.', $context);
        $this->assertStringContainsString('CONSTRAINTS', $context);

        $this->assertStringContainsString(
            'No frontend logic.',
            $this->context($this->fire(['hook_event_name' => 'SessionStart', 'source' => 'resume'])),
            'a resume continues the same plan',
        );
    }

    /**
     * The point of the change: constraints ride the compaction boundary, NOT a tool-use timer. A hook
     * that speaks when nothing prompted it is the thing that trains an agent to skim what does.
     */
    public function test_never_speaks_on_a_tool_use(): void
    {
        $this->writeConstraint();
        PlanMarker::inSession(Workspace::at($this->root))->activate('sha');

        for ($i = 0; $i < 60; $i++) {
            $this->assertSame([], $this->fire(['hook_event_name' => 'PostToolUse', 'tool_name' => 'Edit']), "silent on tool use {$i}");
        }
    }

    /**
     * A `startup`/`clear` is a NEW session — whatever plan state survived is {@see SessionReset}'s to
     * wipe, not ours to re-inject into a session that never asked for it.
     */
    public function test_silent_on_a_fresh_session(): void
    {
        $this->writeConstraint();
        PlanMarker::inSession(Workspace::at($this->root))->activate('sha');

        $this->assertSame([], $this->fire(['hook_event_name' => 'SessionStart', 'source' => 'startup']));
    }

    public function test_silent_when_no_plan_is_active(): void
    {
        $this->writeConstraint(); // constraints exist, but no plan marker

        $this->assertSame([], $this->fire());
    }

    public function test_silent_when_a_plan_has_no_constraints(): void
    {
        PlanMarker::inSession(Workspace::at($this->root))->activate('sha'); // active plan, but no constraints configured

        $this->assertSame([], $this->fire());
    }
}
