<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Cli\Judge;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Backend\Detector;
use JesseGall\CodeCommandments\Cli\Judge\DetectorRunner;
use JesseGall\CodeCommandments\Cli\Judge\Views;
use JesseGall\CodeCommandments\Cli\ProgressBar;
use JesseGall\CodeCommandments\Cli\Report\SinReport;
use JesseGall\CodeCommandments\Cli\Report\SkippedRules;
use JesseGall\CodeCommandments\Finding;
use JesseGall\CodeCommandments\Sins\Backend\ArrayBag;
use JesseGall\CodeCommandments\Sins\Sin;
use PHPUnit\Framework\TestCase;

/**
 * A rule that BREAKS is skipped so the rest of the run survives — but the run must not then read as a
 * clean bill of health (#468). A broken rule had been reporting "no sins found" for an unknown length
 * of time: the exact failure of a check that reports success without having run.
 */
final class BrokenRuleIsNotGreenTest extends TestCase
{
    public function test_a_rule_that_breaks_comes_back_named(): void
    {
        $judgement = new DetectorRunner(1)->run(
            [self::broken(), self::broken()],
            Views::whole(Codebase::fromString('<?php class A {}')),
            new ProgressBar,
        );

        $this->assertSame([], $judgement->findings);
        $this->assertCount(2, $judgement->skipped, 'both rules could not run');
        $this->assertFalse($judgement->isClean(), 'nothing found is not a clean run when a rule never ran');
    }

    public function test_a_run_where_everything_ran_is_clean(): void
    {
        $judgement = new DetectorRunner(1)->run([], Views::whole(Codebase::fromString('<?php class A {}')), new ProgressBar);

        $this->assertTrue($judgement->isClean());
    }

    public function test_the_report_says_which_rules_could_not_run(): void
    {
        // The checklist is what an agent works from, so the gap in the verdict is stated THERE too —
        // not only on a console line that scrolls away.
        $report = new SinReport(
            '/app',
            [new Finding('Rule', 'backend/absence', 'array-bag', '/app/A.php', '/app/A.php:1', 'A::m')],
            skipped: new SkippedRules(['BrokenRule']),
        );

        $this->assertStringContainsString('BrokenRule', $report->console());
        $this->assertStringContainsString('1 rule could not run', $report->console());
        $this->assertStringContainsString('BrokenRule', $report->checklist());
        $this->assertStringContainsString('1 rule could not run', $report->checklist());
    }

    public function test_a_run_with_nothing_skipped_says_nothing_about_skips(): void
    {
        $report = new SinReport(
            '/app',
            [new Finding('Rule', 'backend/absence', 'array-bag', '/app/A.php', '/app/A.php:1', 'A::m')],
        );

        $this->assertStringNotContainsString('could not run', $report->console());
        $this->assertStringNotContainsString('could not run', $report->checklist());
    }

    private static function broken(): Detector
    {
        return new class implements Detector
        {
            public function sin(): Sin
            {
                return new ArrayBag();
            }

            public function find(Codebase $codebase): array
            {
                throw new \RuntimeException('Call to undefined method AstNode::assignsThisProperty');
            }
        };
    }
}
