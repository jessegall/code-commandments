<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Cli\Orchestration;

use JesseGall\CodeCommandments\Cli\Orchestration\Board;
use JesseGall\CodeCommandments\Cli\Orchestration\Claim;
use JesseGall\CodeCommandments\Cli\Orchestration\Stage;
use JesseGall\CodeCommandments\Workspace;
use PHPUnit\Framework\TestCase;

/**
 * Who holds what. Every assertion here is one of the failures that paid for it: the same item claimed
 * twice, a hold freed by a process ending, and a finished piece of work counted as though it were still
 * running.
 */
final class BoardTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/cc-board-' . uniqid('', true);
        mkdir($this->root, 0777, true);
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->root));
    }

    private function board(): Board
    {
        return Board::inSession(new Workspace($this->root, 'sess-1'));
    }

    public function test_a_claim_is_taken_and_read_back(): void
    {
        $this->board()->claim('order-totals', 'lane-a', 'now');

        $claim = $this->board()->on('order-totals')->unwrap();

        $this->assertTrue($claim->isHeldBy('lane-a'));
        $this->assertSame(Stage::Working, $claim->stage);
        $this->assertSame(1, $claim->round);
    }

    /**
     * The failure this exists for: two lanes built the same widget three times in one day, because the
     * protocol asked them to ANNOUNCE and both announced something that did not collide.
     */
    public function test_a_second_claim_on_a_held_item_is_refused(): void
    {
        $board = $this->board();
        $board->claim('order-totals', 'lane-a', 'now');

        $this->assertTrue($board->claim('order-totals', 'lane-b', 'later')->isNone());
        $this->assertTrue($board->on('order-totals')->unwrap()->isHeldBy('lane-a'), 'the first holder keeps it');
    }

    /**
     * A worker's process ends every time it reports — that is the normal cycle, not death. A hold that
     * freed then would free the item in the exact window the orchestrator is deciding what to hand out.
     */
    public function test_reporting_does_not_release_the_hold(): void
    {
        $board = $this->board();
        $board->claim('order-totals', 'lane-a', 'now');
        $board->move('order-totals', Stage::Reported);

        $this->assertTrue($this->board()->on('order-totals')->unwrap()->isHeldBy('lane-a'));
        $this->assertTrue($this->board()->claim('order-totals', 'lane-b', 'later')->isNone());
    }

    public function test_accepting_releases_it(): void
    {
        $board = $this->board();
        $board->claim('order-totals', 'lane-a', 'now');
        $board->move('order-totals', Stage::Accepted);

        $this->assertTrue($this->board()->on('order-totals')->isNone());
        $this->assertTrue($this->board()->claim('order-totals', 'lane-b', 'later')->isSome());
    }

    /**
     * Only work being done occupies a slot. A reported or blocked item waits on the ORCHESTRATOR, and
     * charging it a slot would bill the user for the tool's own queue.
     */
    public function test_only_working_items_occupy_a_slot(): void
    {
        $board = $this->board();
        $board->claim('a', 'lane-a', 'now');
        $board->claim('b', 'lane-b', 'now');
        $board->claim('c', 'lane-c', 'now');
        $board->move('b', Stage::Reported);
        $board->move('c', Stage::Blocked);

        $this->assertCount(1, $board->running());
        $this->assertCount(2, $board->awaiting(), 'both are waiting on a decision');
    }

    /**
     * Three attempts on one item usually means the item was mis-specified, so the count has to survive.
     */
    public function test_rework_keeps_the_hold_and_counts_the_round(): void
    {
        $board = $this->board();
        $board->claim('order-totals', 'lane-a', 'now');
        $board->move('order-totals', Stage::Reported);
        $board->rework('order-totals');

        $claim = $this->board()->on('order-totals')->unwrap();

        $this->assertSame(2, $claim->round);
        $this->assertSame(Stage::Working, $claim->stage);
        $this->assertTrue($claim->isHeldBy('lane-a'), 'rework is the same worker, or its context is wasted');
    }

    /**
     * A worker can simply vanish — `SendMessage` answering "no transcript found for agent id" while the
     * lane is clean and its work committed. The item is then held by nobody, and the hold being bound to
     * the board rather than the process is exactly why it cannot free itself. The item returns to
     * unclaimed, and the record says ABANDONED rather than judged: naming it a replacement would file a
     * decision about work that may have been fine.
     */
    public function test_an_abandoned_item_returns_to_unclaimed(): void
    {
        $board = $this->board();
        $board->claim('payments', 'lane-gone', 'now');
        $board->move('payments', Stage::Abandoned);

        $this->assertTrue($this->board()->on('payments')->isNone(), 'nobody holds it any more');
        $this->assertSame([], $this->board()->running(), 'a vanished holder occupies no slot');
        $this->assertTrue($this->board()->claim('payments', 'lane-new', 'later')->isSome(), 'and somebody else may take it');
    }

    /**
     * Abandoned and accepted both settle the item, and must not read as the same thing: one says the work
     * was judged, the other says nobody was left to judge it.
     */
    public function test_abandoned_is_not_accepted(): void
    {
        $this->assertTrue(Stage::Abandoned->isSettled());
        $this->assertTrue(Stage::Accepted->isSettled());
        $this->assertNotSame(Stage::Accepted->nextAct(), Stage::Abandoned->nextAct());
        $this->assertStringContainsString('claim it again', Stage::Abandoned->nextAct());
    }

    public function test_the_board_keeps_one_line_per_item(): void
    {
        $board = $this->board();
        $board->claim('a', 'lane-a', 'now');
        $board->move('a', Stage::Reported);
        $board->move('a', Stage::Working);

        $this->assertCount(1, $board->claims());
    }

    public function test_a_claim_survives_the_round_trip(): void
    {
        $claim = new Claim('a', new \JesseGall\CodeCommandments\Cli\Orchestration\Hold('lane-a', 'now'), Stage::Blocked, 3);
        $back = Claim::fromLine($claim->toLine())->unwrap();

        $this->assertSame('a', $back->item);
        $this->assertSame(Stage::Blocked, $back->stage);
        $this->assertSame(3, $back->round);
        $this->assertTrue($back->isHeldBy('lane-a'));
    }
}
