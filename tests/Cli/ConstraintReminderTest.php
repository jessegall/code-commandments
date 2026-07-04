<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Cli;

use JesseGall\CodeCommandments\Hooks\Handlers\ConstraintReminder;
use JesseGall\CodeCommandments\Cli\Plan\PlanMarker;
use PHPUnit\Framework\TestCase;

/**
 * The constraint heartbeat: while a plan is active and constraints are in force, it re-surfaces them
 * once every 25 tool uses. Silent otherwise. Driven through a {@see CapturingHookIO} + {@see FakeGit}.
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
    private function fire(): array
    {
        $io = new CapturingHookIO(new FakeGit($this->root), ['hook_event_name' => 'PostToolUse', 'tool_name' => 'Edit']);
        new ConstraintReminder($io)->run([]);

        return $io->emitted;
    }

    private function context(array $emitted): string
    {
        return (string) ($emitted[0]['hookSpecificOutput']['additionalContext'] ?? '');
    }

    private function writeConstraint(): void
    {
        file_put_contents(
            $this->root . '/.commandments/config.php',
            "<?php\nuse JesseGall\\CodeCommandments\\Config;\nreturn function (Config \$config): void {\n"
            . "    \$config->planExecution(fn (\$p) => \$p->constraint('No frontend logic.'));\n};\n",
        );
    }

    public function test_surfaces_the_constraints_once_every_interval_during_a_plan(): void
    {
        $this->writeConstraint();
        PlanMarker::inWorktree($this->root)->activate('sha');

        for ($i = 1; $i < 25; $i++) {
            $this->assertSame([], $this->fire(), "silent on tick {$i}");
        }

        $context = $this->context($this->fire());
        $this->assertStringContainsString('No frontend logic.', $context);
        $this->assertStringContainsString('CONSTRAINTS', $context);

        // Resets after firing — the next 24 are silent again.
        $this->assertSame([], $this->fire(), 'counter reset after the reminder');
    }

    public function test_silent_when_no_plan_is_active(): void
    {
        $this->writeConstraint(); // constraints exist, but no plan marker

        for ($i = 0; $i < 30; $i++) {
            $this->assertSame([], $this->fire());
        }
    }

    public function test_silent_when_a_plan_has_no_constraints(): void
    {
        PlanMarker::inWorktree($this->root)->activate('sha'); // active plan, but no constraints configured

        for ($i = 0; $i < 30; $i++) {
            $this->assertSame([], $this->fire());
        }
    }
}
