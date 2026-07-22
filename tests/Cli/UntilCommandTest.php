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
        $this->assertSame([1 => 'the full test suite passes'], $this->gate()->all());
    }

    public function test_an_unquoted_condition_joins_its_words(): void
    {
        $this->exec('the', 'linter', 'is', 'clean');

        $this->assertSame([1 => 'the linter is clean'], $this->gate()->all());
    }

    public function test_conditions_stack_and_each_gets_its_own_number(): void
    {
        $this->exec('add', 'tests pass');
        $this->exec('add', 'readme updated');

        $this->assertSame([1 => 'tests pass', 2 => 'readme updated'], $this->gate()->all());
        $this->assertSame(0, $this->exec('list'));
    }

    public function test_met_strikes_one_off_and_leaves_the_rest_standing(): void
    {
        $this->exec('add', 'tests pass');
        $this->exec('add', 'readme updated');

        $this->assertSame(0, $this->exec('met', '1'));
        $this->assertSame([2 => 'readme updated'], $this->gate()->all(), 'the survivor KEEPS its id');
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
        $this->assertSame([1 => 'tests pass'], $this->gate()->all());
    }

    public function test_stuck_marks_the_one_shot_pause_without_dropping_the_condition(): void
    {
        $this->exec('add', 'tests pass');

        $this->assertSame(0, $this->exec('stuck'));
        $this->assertTrue($this->gate()->isStuck());
        $this->assertSame([1 => 'tests pass'], $this->gate()->all(), 'the condition stays in force');
    }

    public function test_clear_drops_every_condition(): void
    {
        $this->exec('add', 'tests pass');
        $this->exec('add', 'readme updated');

        $this->exec('clear');

        $this->assertSame([], $this->gate()->all());
    }

    public function test_ids_stay_stable_so_a_batch_of_met_calls_reads_off_one_list(): void
    {
        // The #399 footgun: read the list ONCE, then `met 2` followed by `met 3` — with positional
        // numbering the second call would strike the wrong (renumbered) condition.
        $this->exec('add', 'first');
        $this->exec('add', 'second');
        $this->exec('add', 'third');

        $this->assertSame(0, $this->exec('met', '2'));
        $this->assertSame(0, $this->exec('met', '3'), 'the id read before the first strike still resolves');
        $this->assertSame([1 => 'first'], $this->gate()->all());
    }

    public function test_a_struck_off_id_is_never_reused_by_a_later_add(): void
    {
        $this->exec('add', 'first');
        $this->exec('add', 'second');
        $this->exec('met', '2');

        $this->exec('add', 'third');

        $this->assertSame([1 => 'first', 3 => 'third'], $this->gate()->all());
        $this->assertSame(2, $this->exec('met', '2'), 'a stale met on the struck id is an error, not a mis-strike');
    }

    public function test_a_legacy_marker_without_ids_reads_back_with_positional_ids(): void
    {
        // A pre-stable-ids marker still holds bare condition texts — it must keep gating (never
        // silently lift mid-session on upgrade) and renumber once into stable ids.
        $marker = Workspace::at($this->root)->path('.until');
        mkdir(dirname($marker), 0777, true);
        file_put_contents($marker, "0\n0\ntests pass\nreadme updated\n-----\nlegacy\n");

        $this->assertSame([1 => 'tests pass', 2 => 'readme updated'], $this->gate()->all());
        $this->assertSame(0, $this->exec('met', '1'));
        $this->assertSame([2 => 'readme updated'], $this->gate()->all());
    }

    public function test_list_and_clear_are_quiet_when_no_gate_is_set(): void
    {
        $this->assertSame(0, $this->exec('list'));
        $this->assertSame(0, $this->exec('clear'), 'clearing an unset gate is a no-op, not an error');
    }
}
