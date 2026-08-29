<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Cli\Orchestration;

use JesseGall\CodeCommandments\Cli\Console;
use JesseGall\CodeCommandments\Cli\Input;
use JesseGall\CodeCommandments\Cli\Orchestration\Board;
use JesseGall\CodeCommandments\Cli\Orchestration\BuildCommand;
use JesseGall\CodeCommandments\Cli\Orchestration\Stage;
use JesseGall\CodeCommandments\Tests\Cli\CapturingHookIO;
use JesseGall\CodeCommandments\Tests\Cli\FakeGit;
use JesseGall\CodeCommandments\Workspace;
use PHPUnit\Framework\TestCase;

/**
 * `doctor` and `log` exist for the moment somebody does not know what is going on — which is exactly the
 * moment a remembered number would mislead them, so every line is computed in the invocation that prints
 * it and an absent fact says so in words.
 */
final class DoctorTest extends TestCase
{
    private string $root;

    private string|false $priorProjectDir;

    private string|false $priorSession;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/cc-doctor-' . uniqid('', true);
        mkdir($this->root . '/.commandments', 0777, true);
        $this->priorProjectDir = getenv('CLAUDE_PROJECT_DIR');
        $this->priorSession = getenv('CLAUDE_CODE_SESSION_ID');
        putenv('CLAUDE_PROJECT_DIR=' . $this->root);
        putenv('CLAUDE_CODE_SESSION_ID=doctor-test');
    }

    protected function tearDown(): void
    {
        putenv($this->priorProjectDir === false ? 'CLAUDE_PROJECT_DIR' : 'CLAUDE_PROJECT_DIR=' . $this->priorProjectDir);
        putenv($this->priorSession === false ? 'CLAUDE_CODE_SESSION_ID' : 'CLAUDE_CODE_SESSION_ID=' . $this->priorSession);
        exec('rm -rf ' . escapeshellarg($this->root));
    }

    private function board(): Board
    {
        return Board::inSession(Workspace::ofSession($this->root)); // The same folder the command reads.
    }

    private function build(string ...$argv): string
    {
        $out = fopen('php://memory', 'r+');

        new BuildCommand(new CapturingHookIO(new FakeGit($this->root)), new Console($out))
            ->run(Input::fromArgv(['commandments', 'build', ...$argv]));

        rewind($out);

        return (string) stream_get_contents($out);
    }

    /**
     * An item nothing measured says so, rather than showing nothing and letting a reader assume it passed.
     */
    public function test_an_unmeasured_item_says_it_is_somebodys_word(): void
    {
        $this->board()->claim('payments', 'lane-1', '10:00');

        $this->assertStringContainsString('no receipt', $this->build('log'));
        $this->assertStringContainsString("somebody's word", $this->build('log'));
    }

    public function test_doctor_names_the_act_for_each_item(): void
    {
        $board = $this->board();
        $board->claim('cart', 'lane-2', '10:00');
        $board->move('cart', Stage::Reported);

        $doctor = $this->build('doctor');

        $this->assertStringContainsString('cart', $doctor);
        $this->assertStringContainsString('reported', $doctor);
        $this->assertStringContainsString('accept', $doctor, 'a state without an act tells the reader nothing they can use');
    }

    /**
     * The slot count is counted, not remembered — and it says so, because a number whose age is unknown
     * reads as authoritative.
     */
    public function test_the_slot_count_says_it_was_counted_now(): void
    {
        $this->board()->claim('payments', 'lane-1', '10:00');

        $this->assertStringContainsString('counted now', $this->build('doctor'));
    }

    public function test_doctor_on_no_build_says_there_is_none(): void
    {
        $this->assertStringContainsString('No build here', $this->build('doctor'));
    }

    public function test_the_log_of_nothing_says_nothing_was_measured(): void
    {
        $this->assertStringContainsString('nothing has been measured', $this->build('log'));
    }
}
