<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Cli\Orchestration;

use JesseGall\CodeCommandments\Cli\Orchestration\Receipt;
use PHPUnit\Framework\TestCase;

/**
 * A measurement, not a claim. The trap it exists for is not that a number was derived — it is that a
 * number correct for the LANE was reported as the branch's, which happened three times in one day,
 * identically, and was caught each time by a reviewer rather than by the tool.
 */
final class ReceiptTest extends TestCase
{
    private function receipt(string $head, string $mergeBase, int $exit = 0): Receipt
    {
        return new Receipt('ratchet', 'scripts/gates.sh', $exit, $head, $mergeBase, '14:02');
    }

    public function test_the_exit_code_decides_the_verdict(): void
    {
        $this->assertTrue($this->receipt('aaa', 'bbb')->isGreen());
        $this->assertFalse($this->receipt('aaa', 'bbb', exit: 1)->isGreen());
    }

    /**
     * Two receipts that measured different trees do not agree or disagree — the later supersedes. Without
     * this, a lane's honest number and the branch's contradict each other with no way to tell which is
     * which.
     */
    public function test_two_receipts_of_different_trees_do_not_agree(): void
    {
        $lane = $this->receipt('9f3a1c2', '4d81e0b');
        $branch = $this->receipt('c51eab6', '78ac68a');

        $this->assertFalse($lane->measuredTheSameTreeAs($branch));
        $this->assertTrue($lane->measuredTheSameTreeAs($this->receipt('9f3a1c2', '4d81e0b')));
    }

    /**
     * The tree is printed, because it is the part a reader would otherwise assume.
     */
    public function test_it_names_the_tree_it_measured(): void
    {
        $rendered = $this->receipt('9f3a1c2', '4d81e0b')->render();

        $this->assertStringContainsString('9f3a1c2', $rendered);
        $this->assertStringContainsString('4d81e0b', $rendered);
        $this->assertStringContainsString('scripts/gates.sh', $rendered);
    }

    public function test_a_failure_says_the_code_it_got(): void
    {
        $this->assertStringContainsString('exit 1', $this->receipt('a', 'b', exit: 1)->render());
    }

    public function test_a_receipt_survives_the_round_trip(): void
    {
        $receipt = $this->receipt('9f3a1c2', '4d81e0b', exit: 2);
        $back = Receipt::fromLine($receipt->toLine())->unwrap();

        $this->assertSame('scripts/gates.sh', $back->argv);
        $this->assertSame(2, $back->exitCode);
        $this->assertSame('9f3a1c2', $back->head);
        $this->assertSame('4d81e0b', $back->mergeBase);
    }

    public function test_a_line_this_format_did_not_write_is_skipped(): void
    {
        $this->assertTrue(Receipt::fromLine('somebody typed this')->isNone());
    }
}
