<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Cli\Orchestration;

use JesseGall\CodeCommandments\Cli\Console;
use JesseGall\CodeCommandments\Cli\Input;
use JesseGall\CodeCommandments\Cli\Orchestration\Instance;
use JesseGall\CodeCommandments\Cli\Orchestration\OrchestrateCommand;
use JesseGall\CodeCommandments\Cli\Orchestration\Plan;
use JesseGall\CodeCommandments\Tests\Cli\CapturingHookIO;
use JesseGall\CodeCommandments\Tests\Cli\FakeGit;
use JesseGall\CodeCommandments\Workspace;
use PHPUnit\Framework\TestCase;

/**
 * `go` is a cursor WRITE, and the cheapest proof is the one that caught it missing: move, then ask where
 * you are, and make the two agree. Asserting that `go` renders a plausible tree would have passed while
 * it changed nothing — the verification has to come from the command that CONSUMES the state.
 */
final class PlanCursorTest extends TestCase
{
    private string $root;

    private string|false $priorProjectDir;

    private string|false $priorSession;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/cc-cursor-' . uniqid('', true);
        mkdir($this->root . '/.commandments', 0777, true);
        $this->priorProjectDir = getenv('CLAUDE_PROJECT_DIR');
        $this->priorSession = getenv('CLAUDE_CODE_SESSION_ID');
        putenv('CLAUDE_PROJECT_DIR=' . $this->root);
        putenv('CLAUDE_CODE_SESSION_ID=cursor-test');
    }

    protected function tearDown(): void
    {
        putenv($this->priorProjectDir === false ? 'CLAUDE_PROJECT_DIR' : 'CLAUDE_PROJECT_DIR=' . $this->priorProjectDir);
        putenv($this->priorSession === false ? 'CLAUDE_CODE_SESSION_ID' : 'CLAUDE_CODE_SESSION_ID=' . $this->priorSession);
        exec('rm -rf ' . escapeshellarg($this->root));
    }

    private function exec(string ...$argv): int
    {
        $out = fopen('php://memory', 'r+');

        return new OrchestrateCommand(new CapturingHookIO(new FakeGit($this->root)), new Console($out))
            ->run(Input::fromArgv(['commandments', 'orchestrate', ...$argv]));
    }

    /**
     * @return list<string>
     */
    private function cursor(): array
    {
        return Instance::inSession(Workspace::ofSession($this->root))->at();
    }

    private function aPlanWithTwoLevels(): void
    {
        $this->exec('plan', 'open', 'the port');
        $this->exec('plan', 'add', 'dissolution', 'the main thrust');
        $this->exec('plan', 'add', 'the-enum', 'underneath it');
    }

    public function test_go_up_moves_the_cursor_without_closing(): void
    {
        $this->aPlanWithTwoLevels();
        $this->assertSame(['dissolution', 'the-enum'], $this->cursor());

        $this->assertSame(0, $this->exec('plan', 'go', '..'));

        $this->assertSame(['dissolution'], $this->cursor(), 'the cursor actually moved');
        $this->assertSame(0, $this->exec('plan', 'where'), 'and the level it left is still there');
    }

    public function test_go_bare_returns_to_the_plan(): void
    {
        $this->aPlanWithTwoLevels();

        $this->exec('plan', 'go');

        $this->assertSame([], $this->cursor());
    }

    public function test_go_descends_into_a_child_by_name(): void
    {
        $this->aPlanWithTwoLevels();
        $this->exec('plan', 'go');

        $this->exec('plan', 'go', 'dissolution');

        $this->assertSame(['dissolution'], $this->cursor());
    }

    public function test_go_takes_a_path_from_the_plan(): void
    {
        $this->aPlanWithTwoLevels();
        $this->exec('plan', 'go');

        $this->exec('plan', 'go', 'dissolution/the-enum');

        $this->assertSame(['dissolution', 'the-enum'], $this->cursor());
    }

    public function test_go_to_a_level_that_is_not_there_refuses_and_stays_put(): void
    {
        $this->aPlanWithTwoLevels();

        $this->assertSame(Console::REFUSED, $this->exec('plan', 'go', 'never-existed'));
        $this->assertSame(['dissolution', 'the-enum'], $this->cursor(), 'a refused move changes nothing');
    }

    /**
     * Moving away and adding must produce a SIBLING. This is the whole point of `go`: an orchestrator
     * with several workers is distracted in parallel, and a tree that can only be one deep path cannot
     * hold that.
     */
    public function test_going_up_then_adding_makes_a_sibling(): void
    {
        $this->exec('plan', 'open', 'the port');
        $this->exec('plan', 'add', 'dissolution', 'the main thrust');
        $this->exec('plan', 'go', '..');
        $this->exec('plan', 'add', 'product-defects', 'a walker is still adding to it');

        $this->assertSame(['product-defects'], $this->cursor());
        $this->assertSame(0, $this->exec('plan', 'where'));
    }

    /**
     * A verb this version does not have used to fall through to the tree, printing a plausible screen and
     * answering 0 — so a caller on an older binary saw its command apparently succeed and do nothing.
     */
    public function test_an_unknown_verb_refuses_rather_than_printing_the_tree(): void
    {
        $this->exec('plan', 'open', 'the port');

        $this->assertSame(Console::REFUSED, $this->exec('plan', 'bogus'));
        $this->assertSame(0, $this->exec('plan'), 'the bare form still shows the tree');
    }

    /**
     * Issue #537, reproduced with the two commands that caused it. `add` stands you IN the level it made,
     * so saying it twice — the natural thing when you cannot see the tree — nested `product-defects`
     * inside `product-defects`. The refusal has to be measured BOTH ways: a non-zero exit, and the level
     * genuinely not written, because a command that prints a complaint and creates the folder anyway is
     * the shape that let this through.
     */
    public function test_adding_a_level_inside_one_of_its_own_name_is_refused(): void
    {
        $this->exec('plan', 'open', 'the port');
        $this->exec('plan', 'add', 'product-defects', 'a walker is filing into it');

        $this->assertSame(Console::REFUSED, $this->exec('plan', 'add', 'product-defects', 'again, blind'));

        $this->assertFalse($this->plan()->has(['product-defects', 'product-defects']), 'nothing was created');
        $this->assertSame(['product-defects'], $this->cursor(), 'and the cursor did not move');
    }

    /**
     * The guard is the whole PATH, not the level you stand on — a name two levels up is just as much a
     * breadcrumb that reads back ambiguously.
     */
    public function test_the_name_of_any_level_on_the_path_is_refused(): void
    {
        $this->exec('plan', 'open', 'the port');
        $this->exec('plan', 'add', 'dissolution', 'the main thrust');
        $this->exec('plan', 'add', 'the-enum', 'underneath it');

        $this->assertSame(Console::REFUSED, $this->exec('plan', 'add', 'dissolution', 'blind again'));
        $this->assertSame(['dissolution', 'the-enum'], $this->cursor());
    }

    /**
     * And the guard costs nothing it should not: a DIFFERENT name still nests, which is the ordinary use
     * the refusal must not have made harder.
     */
    public function test_a_different_name_still_nests(): void
    {
        $this->exec('plan', 'open', 'the port');
        $this->exec('plan', 'add', 'product-defects', 'a walker is filing into it');

        $this->assertSame(0, $this->exec('plan', 'add', 'the-enum', 'underneath it'));
        $this->assertSame(['product-defects', 'the-enum'], $this->cursor());
    }

    /**
     * A sibling of the same name is a different mistake with a different answer — it is refused by the
     * plan itself ("already a sidequest here"), and asserting it here keeps the two guards from being
     * mistaken for one.
     */
    public function test_a_sibling_of_the_same_name_is_still_refused_by_the_plan(): void
    {
        $this->exec('plan', 'open', 'the port');
        $this->exec('plan', 'add', 'dissolution', 'the main thrust');
        $this->exec('plan', 'go', '..');

        $this->assertSame(Console::REFUSED, $this->exec('plan', 'add', 'dissolution', 'the same name beside itself'));
        $this->assertSame([], $this->cursor(), 'a refused add leaves you where you were');
    }

    private function plan(): Plan
    {
        return Plan::inSession(Workspace::ofSession($this->root));
    }
}
