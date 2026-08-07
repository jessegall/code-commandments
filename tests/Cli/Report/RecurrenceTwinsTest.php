<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Cli\Report;

use JesseGall\CodeCommandments\Finding;
use JesseGall\CodeCommandments\Cli\Report\SinReport;
use PHPUnit\Framework\TestCase;

/**
 * A RECURRENCE sin is a verdict about a SET — this shape appears in ≥2 places — yet the report used to
 * print each occurrence alone, so a reader opening one site saw nothing wrong with it and could not tell
 * WHAT it was bucketed with (least of all when the twin lived in another file). The report names the
 * siblings, and only for the detectors that actually bucket.
 */
final class RecurrenceTwinsTest extends TestCase
{
    /** @param list<string> $twins */
    private function finding(string $location, array $twins = []): Finding
    {
        return new Finding('RepeatedGuardDetector', 'backend/repeated-call-helper', 'repeated-guard', '/app/Gate.php', $location, 'Gate::allow', $twins);
    }

    public function test_names_the_sibling_occurrences(): void
    {
        $report = new SinReport('/app', [$this->finding('/app/Gate.php:18', ['/app/Audit.php:26'])]);

        $this->assertStringContainsString('same shape as: Audit.php:26', $report->console());
        $this->assertStringContainsString('same shape as Audit.php:26', $report->checklist());
    }

    public function test_says_nothing_when_a_finding_stands_alone(): void
    {
        $report = new SinReport('/app', [$this->finding('/app/Gate.php:18')]);

        $this->assertStringNotContainsString('same shape as', $report->console());
        $this->assertStringNotContainsString('same shape as', $report->checklist());
    }

    public function test_caps_the_list_so_a_shape_repeated_everywhere_stays_readable(): void
    {
        $twins = ['/app/A.php:1', '/app/B.php:2', '/app/C.php:3', '/app/D.php:4', '/app/E.php:5'];

        $console = new SinReport('/app', [$this->finding('/app/Gate.php:18', $twins)])->console();

        $this->assertStringContainsString('A.php:1, B.php:2, C.php:3 (+2 more)', $console);
        $this->assertStringNotContainsString('D.php:4', $console);
    }
}
