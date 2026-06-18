<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Tracking;

use Illuminate\Filesystem\Filesystem;
use JesseGall\CodeCommandments\Tracking\JsonConfessionTracker;
use JesseGall\CodeCommandments\Tests\TestCase;

class JsonConfessionTrackerTest extends TestCase
{
    private string $tablet;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tablet = tempnam(sys_get_temp_dir(), 'cc-tracker-') . '.json';
        @unlink($this->tablet);
    }

    protected function tearDown(): void
    {
        @unlink($this->tablet);
        parent::tearDown();
    }

    private function tracker(): JsonConfessionTracker
    {
        return new JsonConfessionTracker($this->tablet, new Filesystem());
    }

    public function test_absolves_and_recognises_a_finding(): void
    {
        $tracker = $this->tracker();

        $this->assertFalse($tracker->isFindingAbsolved('abc123'));

        $tracker->absolveFinding('abc123', 'only one caller, local case');

        // A fresh tracker reads it back from disk.
        $this->assertTrue($this->tracker()->isFindingAbsolved('abc123'));
    }

    public function test_gc_drops_unseen_findings_but_keeps_seen_ones(): void
    {
        $tracker = $this->tracker();
        $tracker->absolveFinding('seen-one');
        $tracker->absolveFinding('gone-one');

        $tracker->markFindingSeen('seen-one');
        $removed = $tracker->gcUnseenFindings();

        $this->assertSame(1, $removed);
        $this->assertTrue($tracker->isFindingAbsolved('seen-one'));
        $this->assertFalse($tracker->isFindingAbsolved('gone-one'));
    }

    public function test_clears_all_finding_absolutions(): void
    {
        $tracker = $this->tracker();
        $tracker->absolveFinding('one');
        $tracker->absolveFinding('two');

        $cleared = $tracker->clearFindingAbsolutions();

        $this->assertSame(2, $cleared);
        $this->assertFalse($this->tracker()->isFindingAbsolved('one'));
        $this->assertFalse($this->tracker()->isFindingAbsolved('two'));
    }

    public function test_reported_finding_is_recognised_as_absolved(): void
    {
        $tracker = $this->tracker();

        $this->assertFalse($tracker->isFindingAbsolved('fp-A'));
        $this->assertFalse($tracker->isFindingReported('fp-A'));

        $tracker->reportFinding('fp-A', 'genuine false positive', 32, 'owner/repo');

        // A fresh tracker reads it back from disk as BOTH reported and absolved.
        $fresh = $this->tracker();
        $this->assertTrue($fresh->isFindingReported('fp-A'));
        $this->assertTrue($fresh->isFindingAbsolved('fp-A'));
    }

    public function test_reporting_one_fingerprint_does_not_absolve_another(): void
    {
        // The whole guardrail: a report absolves ONLY the exact finding it names,
        // never a sibling finding, never the rest of the prophet's findings.
        $tracker = $this->tracker();

        $tracker->reportFinding('fp-A', 'false positive', 32, 'owner/repo');

        $this->assertTrue($tracker->isFindingAbsolved('fp-A'));
        $this->assertFalse($tracker->isFindingAbsolved('fp-B'));
        $this->assertFalse($tracker->isFindingReported('fp-B'));
    }

    public function test_reported_absolution_survives_the_post_commit_clear(): void
    {
        // clearFindingAbsolutions() is the post-commit reset. Ordinary
        // absolutions go; report-linked ones stay until the issue is answered.
        $tracker = $this->tracker();
        $tracker->absolveFinding('ordinary');
        $tracker->reportFinding('reported', 'false positive', 32, 'owner/repo');

        $cleared = $tracker->clearFindingAbsolutions();

        // Only the ordinary absolution is counted/cleared.
        $this->assertSame(1, $cleared);

        $fresh = $this->tracker();
        $this->assertFalse($fresh->isFindingAbsolved('ordinary'));
        $this->assertTrue($fresh->isFindingAbsolved('reported'));
        $this->assertTrue($fresh->isFindingReported('reported'));
    }

    public function test_reported_absolution_is_not_garbage_collected_when_unseen(): void
    {
        // GC drops unseen ORDINARY absolutions; a reported one must persist even
        // when its file was not scanned this run (issue still open).
        $tracker = $this->tracker();
        $tracker->absolveFinding('ordinary-unseen');
        $tracker->reportFinding('reported-unseen', 'false positive', 32, 'owner/repo');

        $removed = $tracker->gcUnseenFindings();

        $this->assertSame(1, $removed);
        $this->assertFalse($tracker->isFindingAbsolved('ordinary-unseen'));
        $this->assertTrue($tracker->isFindingAbsolved('reported-unseen'));
    }

    public function test_release_removes_only_the_named_report(): void
    {
        $tracker = $this->tracker();
        $tracker->reportFinding('fp-A', 'false positive', 32, 'owner/repo');
        $tracker->reportFinding('fp-B', 'wrong rule', 33, 'owner/repo');

        $tracker->releaseReportedFinding('fp-A');

        $fresh = $this->tracker();
        $this->assertFalse($fresh->isFindingAbsolved('fp-A'));
        $this->assertFalse($fresh->isFindingReported('fp-A'));
        // The other report is untouched — release is per-fingerprint.
        $this->assertTrue($fresh->isFindingReported('fp-B'));
    }

    public function test_releasing_an_unknown_fingerprint_is_a_noop(): void
    {
        $tracker = $this->tracker();
        $tracker->reportFinding('fp-A', 'false positive', 32, 'owner/repo');

        $tracker->releaseReportedFinding('never-reported');

        $this->assertTrue($tracker->isFindingAbsolved('fp-A'));
    }

    public function test_reported_findings_exposes_issue_metadata(): void
    {
        $tracker = $this->tracker();
        $tracker->reportFinding('fp-A', 'false positive', 32, 'owner/repo');

        $reported = $this->tracker()->reportedFindings();

        $this->assertArrayHasKey('fp-A', $reported);
        $this->assertSame(32, $reported['fp-A']['issue']);
        $this->assertSame('owner/repo', $reported['fp-A']['repo']);
        $this->assertSame('false positive', $reported['fp-A']['reason']);
    }

    public function test_ordinary_absolution_alongside_a_report_clears_independently(): void
    {
        // A fingerprint that is both ordinarily absolved AND reported stays
        // absolved through a post-commit clear via the report link.
        $tracker = $this->tracker();
        $tracker->absolveFinding('fp-A', 'ordinary reason');
        $tracker->reportFinding('fp-A', 'false positive', 32, 'owner/repo');

        $tracker->clearFindingAbsolutions();

        $this->assertTrue($this->tracker()->isFindingAbsolved('fp-A'));
    }

    public function test_reads_legacy_flat_format(): void
    {
        // Legacy file: a top-level path => commandment map, no wrapper keys.
        file_put_contents($this->tablet, json_encode([
            'src/Foo.php' => [
                'App\\Prophet' => [
                    'absolved_at' => '2020-01-01T00:00:00+00:00',
                    'reason' => 'legacy',
                    'content_hash' => md5('x'),
                ],
            ],
        ]));

        $tracker = $this->tracker();

        // The legacy file-level absolutions survive the format migration.
        $this->assertArrayHasKey('src/Foo.php', $tracker->getAllAbsolutions());

        // And finding-level operations work alongside them.
        $tracker->absolveFinding('new-finding');
        $this->assertTrue($tracker->isFindingAbsolved('new-finding'));
    }
}
