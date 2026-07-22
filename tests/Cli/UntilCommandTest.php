<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Cli;

use JesseGall\CodeCommandments\Cli\Input;
use JesseGall\CodeCommandments\Cli\Until\UntilGate;
use JesseGall\CodeCommandments\Cli\Until\UntilCommand;
use JesseGall\CodeCommandments\Workspace;
use PHPUnit\Framework\TestCase;

/**
 * `commandments until` — the agent's handle on the user's stop gate: set a condition, list what
 * stands, strike one off as met, pause once when blocked, drop the gate.
 */
final class UntilCommandTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/cc-until-' . uniqid('', true);
        mkdir($this->root . '/.commandments', 0777, true);
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->root));
    }

    private function exec(string ...$args): int
    {
        $command = new UntilCommand(new CapturingHookIO(new FakeGit($this->root)));

        ob_start();
        $code = $command->run(Input::of('until', $args));
        ob_get_clean();

        return $code;
    }

    private function gate(): UntilGate
    {
        return UntilGate::inSession(Workspace::at($this->root));
    }

    public function test_a_bare_condition_sets_the_gate(): void
    {
        $this->assertSame(0, $this->exec('the full test suite passes'));
        $this->assertSame(['the full test suite passes'], $this->gate()->all());
    }

    public function test_an_unquoted_condition_joins_its_words(): void
    {
        $this->exec('the', 'linter', 'is', 'clean');

        $this->assertSame(['the linter is clean'], $this->gate()->all());
    }

    public function test_conditions_stack_and_each_gets_its_own_number(): void
    {
        $this->exec('add', 'tests pass');
        $this->exec('add', 'readme updated');

        $this->assertSame(['tests pass', 'readme updated'], $this->gate()->all());
        $this->assertSame(0, $this->exec('list'));
    }

    public function test_met_strikes_one_off_and_leaves_the_rest_standing(): void
    {
        $this->exec('add', 'tests pass');
        $this->exec('add', 'readme updated');

        $this->assertSame(0, $this->exec('met', '1'));
        $this->assertSame(['readme updated'], $this->gate()->all());
    }

    public function test_meeting_the_last_condition_lifts_the_gate(): void
    {
        $this->exec('add', 'tests pass');

        $this->exec('met', '1');

        $this->assertFalse($this->gate()->isOpen());
    }

    public function test_met_with_an_unknown_number_is_an_error_and_changes_nothing(): void
    {
        $this->exec('add', 'tests pass');

        $this->assertSame(2, $this->exec('met', '7'));
        $this->assertSame(['tests pass'], $this->gate()->all());
    }

    public function test_stuck_marks_the_one_shot_pause_without_dropping_the_condition(): void
    {
        $this->exec('add', 'tests pass');

        $this->assertSame(0, $this->exec('stuck'));
        $this->assertTrue($this->gate()->isStuck());
        $this->assertSame(['tests pass'], $this->gate()->all(), 'the condition stays in force');
    }

    public function test_clear_drops_every_condition(): void
    {
        $this->exec('add', 'tests pass');
        $this->exec('add', 'readme updated');

        $this->exec('clear');

        $this->assertSame([], $this->gate()->all());
    }

    public function test_list_and_clear_are_quiet_when_no_gate_is_set(): void
    {
        $this->assertSame(0, $this->exec('list'));
        $this->assertSame(0, $this->exec('clear'), 'clearing an unset gate is a no-op, not an error');
    }
}
