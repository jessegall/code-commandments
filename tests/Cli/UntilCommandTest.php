<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Cli;

use JesseGall\CodeCommandments\Cli\Input;
use JesseGall\CodeCommandments\Cli\MarkerFile;
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

    /**
     * Plant a LIVE marker holding $conditions (`id<TAB>text` lines) — the only way to reach the state a
     * pre-#418 session could leave behind: conditions in force WHILE the gate stands paused.
     */
    private function live(string ...$conditions): void
    {
        $marker = new MarkerFile(Workspace::at($this->root)->path('.until'));
        $lastId = 0;

        foreach ($conditions as $condition) {
            $lastId = max($lastId, (int) explode("\t", $condition)[0]);
        }

        $marker->write(['0', '0', (string) $lastId, ...$conditions], 'planted by a test');
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

    public function test_stuck_is_a_challenge_not_a_refusal_when_other_conditions_stand(): void
    {
        // `stuck` claims the WHOLE list is blocked, so it prints the others back at the agent — but
        // the signal is always honoured: a genuinely blocked agent is never trapped by the challenge.
        $this->exec('add', 'the migration runs');
        $this->exec('add', 'the changelog has an entry');

        $this->assertSame(0, $this->exec('stuck'));
        $this->assertTrue($this->gate()->isStuck());
        $this->assertCount(2, $this->gate()->all(), 'and every condition stays in force');
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

    public function test_pause_sets_the_whole_gate_aside_and_resume_puts_it_back(): void
    {
        $this->exec('add', 'tests pass');
        $this->exec('add', 'readme updated');

        $this->assertSame(0, $this->exec('pause'));
        $this->assertSame([], $this->gate()->all(), 'nothing holds a stop while paused');
        $this->assertTrue($this->gate()->isPaused());

        $this->assertSame(0, $this->exec('resume'));
        $this->assertFalse($this->gate()->isPaused());
        $this->assertSame([1 => 'tests pass', 2 => 'readme updated'], $this->gate()->all(), 'ids and all');
    }

    public function test_unpause_is_the_same_as_resume(): void
    {
        $this->exec('add', 'tests pass');
        $this->exec('pause');

        $this->assertSame(0, $this->exec('unpause'));
        $this->assertSame([1 => 'tests pass'], $this->gate()->all());
    }

    public function test_a_condition_set_while_paused_waits_with_the_paused_ones_and_re_arms_nothing(): void
    {
        // #418: the user's pause says NOTHING holds — `add` used to write the live marker anyway, so the
        // very next condition silently re-armed the gate and `list` showed a split state.
        $this->exec('add', 'tests pass');
        $this->exec('pause');

        $this->assertSame(0, $this->exec('add', 'the docs build'));

        $this->assertSame([], $this->gate()->all(), 'the pause still holds nothing');
        $this->assertSame([1 => 'tests pass', 2 => 'the docs build'], $this->gate()->pausedConditions());

        $this->exec('resume');

        $this->assertSame([1 => 'tests pass', 2 => 'the docs build'], $this->gate()->all(), 'both come back');
    }

    public function test_a_condition_set_while_paused_continues_the_paused_ids(): void
    {
        $this->exec('add', 'A');
        $this->exec('add', 'B');
        $this->exec('met', '1');   // live: [2 => B], lastId 2
        $this->exec('pause');

        $this->exec('add', 'C');

        $this->assertSame([2 => 'B', 3 => 'C'], $this->gate()->pausedConditions(), 'C continues past B');
    }

    public function test_setting_the_same_condition_twice_keeps_one_and_returns_its_id(): void
    {
        $this->exec('add', 'A');

        $this->assertSame(0, $this->exec('add', 'A'));
        $this->assertSame([1 => 'A'], $this->gate()->all(), 'a condition IS its text — never a twin');
    }

    public function test_met_says_the_gate_is_paused_rather_than_denying_the_condition(): void
    {
        $this->exec('add', 'tests pass');
        $this->exec('pause');

        $this->assertSame(2, $this->exec('met', '1'), 'a paused condition is not struck off');
        $this->assertSame([1 => 'tests pass'], $this->gate()->pausedConditions(), 'and it survives intact');
    }

    public function test_a_split_gate_left_by_an_older_session_folds_back_together(): void
    {
        // #403/#418: a version before the fix wrote a condition set mid-pause to the LIVE marker, so a
        // session can be upgraded mid-flight with both markers on disk. Neither side may be lost:
        // pausing merges into what is set aside, resuming folds the set-aside ones in behind the live.
        $this->exec('add', 'A');
        $this->exec('pause');
        $this->live("1\tB");

        $this->assertSame(0, $this->exec('pause'));

        $this->assertSame([1 => 'A', 2 => 'B'], $this->gate()->pausedConditions(), 'B continues past A');
        $this->assertSame([], $this->gate()->all(), 'and nothing is left holding');
    }

    public function test_resume_folds_the_paused_conditions_in_behind_a_split_gates_live_ones(): void
    {
        $this->exec('add', 'A');
        $this->exec('pause');
        $this->live("1\tB");

        $this->assertSame(0, $this->exec('resume'));

        $this->assertSame([1 => 'B', 2 => 'A'], $this->gate()->all());
        $this->assertFalse($this->gate()->isPaused());
    }

    public function test_pausing_the_same_condition_twice_does_not_double_it(): void
    {
        $this->exec('add', 'A');
        $this->exec('pause');
        $this->exec('add', 'A');

        $this->exec('pause');

        $this->assertSame([1 => 'A'], $this->gate()->pausedConditions());
    }

    public function test_resume_brings_a_re_set_condition_back_only_once(): void
    {
        $this->exec('add', 'A');
        $this->exec('pause');
        $this->exec('add', 'A');

        $this->exec('resume');

        $this->assertSame([1 => 'A'], $this->gate()->all());
    }

    public function test_meeting_a_split_gates_live_condition_leaves_the_paused_one_intact(): void
    {
        $this->exec('add', 'tests pass');
        $this->exec('pause');
        $this->live("1\tthe docs build");

        $this->exec('met', '1');

        $this->assertTrue($this->gate()->isPaused(), 'the paused gate is untouched state, not an empty gate');
        $this->assertSame([1 => 'tests pass'], $this->gate()->pausedConditions());
    }

    public function test_list_shows_what_is_paused_alongside_what_stands(): void
    {
        $this->exec('add', 'tests pass');
        $this->exec('pause');

        $this->assertSame(0, $this->exec('list'));
        $this->assertSame([1 => 'tests pass'], $this->gate()->pausedConditions());
    }

    public function test_clear_drops_a_paused_gate_too(): void
    {
        $this->exec('add', 'tests pass');
        $this->exec('pause');

        $this->assertSame(0, $this->exec('clear'));
        $this->assertFalse($this->gate()->isPaused());
    }

    public function test_pause_and_resume_are_quiet_when_there_is_nothing_to_do(): void
    {
        $this->assertSame(0, $this->exec('pause'), 'no gate — nothing to pause');
        $this->assertSame(0, $this->exec('resume'), 'nothing paused');
        $this->assertFalse($this->gate()->isPaused());
    }

    public function test_list_and_clear_are_quiet_when_no_gate_is_set(): void
    {
        $this->assertSame(0, $this->exec('list'));
        $this->assertSame(0, $this->exec('clear'), 'clearing an unset gate is a no-op, not an error');
    }
}
