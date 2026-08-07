<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Cli;

use JesseGall\CodeCommandments\Hooks\Handlers\TestingReminder;
use JesseGall\CodeCommandments\Cli\Plan\PlanMarker;
use JesseGall\CodeCommandments\Cli\Plan\PlanTesting;
use JesseGall\CodeCommandments\PlanExecution;
use JesseGall\CodeCommandments\Workspace;
use PHPUnit\Framework\TestCase;

/**
 * The testing-methodology heartbeat: while a plan is active and a methodology is in force, it
 * re-surfaces it once every 25 tool uses. Silent otherwise. Driven through a {@see CapturingHookIO}.
 */
final class TestingReminderTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/cc-treminder-' . uniqid('', true);
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
        new TestingReminder($io)->run([]);

        return $io->emitted;
    }

    private function context(array $emitted): string
    {
        return $emitted[0]->context->unwrapOr('');
    }

    private function chooseMethod(): void
    {
        PlanTesting::inSession(Workspace::at($this->root), new PlanExecution()->build())->set('Only fix broken tests.');
    }

    public function test_surfaces_the_methodology_once_every_interval_during_a_plan(): void
    {
        $this->chooseMethod();
        PlanMarker::inSession(Workspace::at($this->root))->activate('sha');

        for ($i = 1; $i < 25; $i++) {
            $this->assertSame([], $this->fire(), "silent on tick {$i}");
        }

        $context = $this->context($this->fire());
        $this->assertStringContainsString('Only fix broken tests.', $context);
        $this->assertStringContainsString('TESTING METHODOLOGY', $context);

        $this->assertSame([], $this->fire(), 'counter reset after the reminder');
    }

    public function test_falls_back_to_the_configured_default(): void
    {
        file_put_contents(
            $this->root . '/.commandments/config.php',
            "<?php\nuse JesseGall\\CodeCommandments\\Config;\nreturn function (Config \$config): void {\n"
            . "    \$config->planExecution(fn (\$p) => \$p->testFlow('Write tests each phase.'));\n};\n",
        );
        PlanMarker::inSession(Workspace::at($this->root))->activate('sha');

        for ($i = 1; $i < 25; $i++) {
            $this->fire();
        }

        $this->assertStringContainsString('Write tests each phase.', $this->context($this->fire()));
    }

    public function test_silent_when_no_plan_is_active(): void
    {
        $this->chooseMethod(); // a methodology exists, but no plan marker

        for ($i = 0; $i < 30; $i++) {
            $this->assertSame([], $this->fire());
        }
    }

    public function test_silent_when_a_plan_has_no_methodology(): void
    {
        PlanMarker::inSession(Workspace::at($this->root))->activate('sha'); // active plan, nothing configured or chosen

        for ($i = 0; $i < 30; $i++) {
            $this->assertSame([], $this->fire());
        }
    }
}
