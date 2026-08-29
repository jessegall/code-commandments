<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Cli\Orchestration;

use JesseGall\CodeCommandments\Cli\Console;
use JesseGall\CodeCommandments\Cli\Input;
use JesseGall\CodeCommandments\Cli\Orchestration\Board;
use JesseGall\CodeCommandments\Cli\Orchestration\BuildCommand;
use JesseGall\CodeCommandments\Tests\Cli\CapturingHookIO;
use JesseGall\CodeCommandments\Tests\Cli\FakeGit;
use JesseGall\CodeCommandments\Workspace;
use PHPUnit\Framework\TestCase;

/**
 * A worker's first instruction is to claim its item, and it chains the work behind that claim:
 * `build claim <item> --by=me && <work>`. So a refusal that exits 0 is not a refusal — the shell reads
 * it as a grant and the collision the hold exists to prevent happens anyway, visible to nobody but a
 * person reading the line.
 */
final class RefusalExitsNonZeroTest extends TestCase
{
    private string $root;

    private string|false $priorProjectDir;

    private string|false $priorSession;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/cc-refusal-' . uniqid('', true);
        mkdir($this->root . '/.commandments', 0777, true);
        $this->priorProjectDir = getenv('CLAUDE_PROJECT_DIR');
        $this->priorSession = getenv('CLAUDE_CODE_SESSION_ID');
        putenv('CLAUDE_PROJECT_DIR=' . $this->root);
        putenv('CLAUDE_CODE_SESSION_ID=refusal-test');
    }

    protected function tearDown(): void
    {
        putenv($this->priorProjectDir === false ? 'CLAUDE_PROJECT_DIR' : 'CLAUDE_PROJECT_DIR=' . $this->priorProjectDir);
        putenv($this->priorSession === false ? 'CLAUDE_CODE_SESSION_ID' : 'CLAUDE_CODE_SESSION_ID=' . $this->priorSession);
        exec('rm -rf ' . escapeshellarg($this->root));
    }

    private function board(): Board
    {
        return Board::inSession(Workspace::ofSession($this->root));
    }

    private function exitCodeOf(string ...$argv): int
    {
        $out = fopen('php://memory', 'r+');

        return new BuildCommand(new CapturingHookIO(new FakeGit($this->root)), new Console($out))
            ->run(Input::fromArgv(['commandments', 'build', ...$argv]));
    }

    /**
     * The collision this whole mechanism exists to stop, in the form a worker actually meets it.
     */
    public function test_a_second_claim_on_a_held_item_refuses(): void
    {
        $this->board()->claim('payments', 'lane-1', '10:00');

        $this->assertSame(Console::REFUSED, $this->exitCodeOf('claim', 'payments', '--by=lane-2'));
    }

    public function test_a_granted_claim_succeeds(): void
    {
        $this->assertSame(0, $this->exitCodeOf('claim', 'payments', '--by=lane-1'));
    }

    /**
     * Every verb that declines because nobody holds the item. Each one is chained behind by something.
     */
    public function test_acting_on_an_item_nobody_holds_refuses(): void
    {
        $required = ['release' => ['--reason=gone']]; // A verb with its own mandatory flag, so the unheld path is the one under test.

        foreach (['report', 'accept', 'release', 'orphan'] as $verb) {
            $this->assertSame(
                Console::REFUSED,
                $this->exitCodeOf($verb, 'never-claimed', ...($required[$verb] ?? [])),
                "`build {$verb}` on an unheld item must refuse",
            );
        }
    }

    public function test_a_claim_with_no_holder_refuses(): void
    {
        $this->assertSame(Console::REFUSED, $this->exitCodeOf('claim', 'payments'));
    }

    /**
     * A verb whose own mandatory flag is missing refuses too — the caller is chaining behind it just the
     * same, and a silent 0 there is the identical failure.
     */
    public function test_a_verb_missing_its_required_reason_refuses(): void
    {
        $this->board()->claim('payments', 'lane-1', '10:00');

        $this->assertSame(Console::REFUSED, $this->exitCodeOf('rework', 'payments'));
        $this->assertSame(Console::REFUSED, $this->exitCodeOf('release', 'payments'));
    }

    /**
     * Reading the build is never a refusal — a status screen that exited non-zero would break every
     * script that merely looks.
     */
    public function test_reading_the_board_succeeds(): void
    {
        $this->board()->claim('payments', 'lane-1', '10:00');

        $this->assertSame(0, $this->exitCodeOf());
        $this->assertSame(0, $this->exitCodeOf('log'));
        $this->assertSame(0, $this->exitCodeOf('doctor'));
    }
}
