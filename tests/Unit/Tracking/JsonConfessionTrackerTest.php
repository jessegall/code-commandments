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
