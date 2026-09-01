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
 * The testing-methodology recall: while a plan is active and a methodology is in force, a compaction or
 * resume re-surfaces it — and no tool use ever does. Driven through a {@see CapturingHookIO}.
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
    private function fire(array $payload = ['hook_event_name' => 'SessionStart', 'source' => 'compact']): array
    {
        $io = new CapturingHookIO(new FakeGit($this->root), $payload);
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

    public function test_surfaces_the_methodology_when_a_compaction_continues_the_plan(): void
    {
        $this->chooseMethod();
        PlanMarker::inSession(Workspace::at($this->root))->activate('sha');

        $context = $this->context($this->fire());
        $this->assertStringContainsString('Only fix broken tests.', $context);
        $this->assertStringContainsString('TESTING METHODOLOGY', $context);

        $this->assertStringContainsString(
            'Only fix broken tests.',
            $this->context($this->fire(['hook_event_name' => 'SessionStart', 'source' => 'resume'])),
            'a resume continues the same plan',
        );
    }

    /**
     * The methodology rides the compaction boundary, never a tool-use timer — see the sibling assertion
     * in {@see ConstraintReminderTest}.
     */
    public function test_never_speaks_on_a_tool_use(): void
    {
        $this->chooseMethod();
        PlanMarker::inSession(Workspace::at($this->root))->activate('sha');

        for ($i = 0; $i < 60; $i++) {
            $this->assertSame([], $this->fire(['hook_event_name' => 'PostToolUse', 'tool_name' => 'Edit']), "silent on tool use {$i}");
        }
    }

    public function test_silent_on_a_fresh_session(): void
    {
        $this->chooseMethod();
        PlanMarker::inSession(Workspace::at($this->root))->activate('sha');

        $this->assertSame([], $this->fire(['hook_event_name' => 'SessionStart', 'source' => 'startup']));
    }

    public function test_falls_back_to_the_configured_default(): void
    {
        file_put_contents(
            $this->root . '/.commandments/config.php',
            "<?php\nuse JesseGall\\CodeCommandments\\Config;\nreturn function (Config \$config): void {\n"
            . "    \$config->planExecution(fn (\$p) => \$p->testFlow('Write tests each phase.'));\n};\n",
        );
        PlanMarker::inSession(Workspace::at($this->root))->activate('sha');

        $this->assertStringContainsString('Write tests each phase.', $this->context($this->fire()));
    }

    public function test_silent_when_no_plan_is_active(): void
    {
        $this->chooseMethod(); // a methodology exists, but no plan marker

        $this->assertSame([], $this->fire());
    }

    public function test_silent_when_a_plan_has_no_methodology(): void
    {
        PlanMarker::inSession(Workspace::at($this->root))->activate('sha'); // active plan, nothing configured or chosen

        $this->assertSame([], $this->fire());
    }
}
