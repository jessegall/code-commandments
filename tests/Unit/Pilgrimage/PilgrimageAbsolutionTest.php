<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Pilgrimage;

use JesseGall\CodeCommandments\Contracts\ConfessionTracker;
use JesseGall\CodeCommandments\Prophets\Backend\LongMethodProphet;
use JesseGall\CodeCommandments\Support\CallGraph\CodebaseIndex;
use JesseGall\CodeCommandments\Support\Pilgrimage\Pilgrimage;
use PHPUnit\Framework\TestCase;

/**
 * #213 — the walk must skip findings the consumer has absolved or reported, so
 * absolving a false positive lets `next` advance instead of blocking forever.
 */
class PilgrimageAbsolutionTest extends TestCase
{
    private string $file;

    protected function setUp(): void
    {
        $dir = sys_get_temp_dir() . '/cc-pilg-abs-' . uniqid();
        mkdir($dir);
        $this->file = $dir . '/Big.php';

        $body = str_repeat("        \$x = 1; if (\$x) { echo \$x; }\n", 30);
        file_put_contents($this->file, "<?php\nclass Big {\n    public function huge(): void {\n{$body}    }\n}\n");
    }

    protected function tearDown(): void
    {
        @unlink($this->file);
        @rmdir(dirname($this->file));
    }

    public function test_a_finding_fires_without_a_tracker(): void
    {
        $locations = (new Pilgrimage)->scanProphet(new LongMethodProphet, [$this->file], new CodebaseIndex, dirname($this->file));

        $this->assertCount(1, $locations);
    }

    public function test_an_absolved_finding_is_skipped(): void
    {
        $tracker = $this->createMock(ConfessionTracker::class);
        $tracker->method('isFindingAbsolved')->willReturn(true);
        $tracker->method('isFindingReported')->willReturn(false);

        $locations = (new Pilgrimage)->scanProphet(new LongMethodProphet, [$this->file], new CodebaseIndex, dirname($this->file), $tracker);

        $this->assertSame([], $locations, 'an absolved finding must not block the walk');
    }

    public function test_a_reported_finding_is_skipped(): void
    {
        $tracker = $this->createMock(ConfessionTracker::class);
        $tracker->method('isFindingAbsolved')->willReturn(false);
        $tracker->method('isFindingReported')->willReturn(true);

        $locations = (new Pilgrimage)->scanProphet(new LongMethodProphet, [$this->file], new CodebaseIndex, dirname($this->file), $tracker);

        $this->assertSame([], $locations, 'a reported finding must stay quiet until its issue is answered');
    }

    public function test_an_unrelated_absolution_does_not_hide_the_finding(): void
    {
        $tracker = $this->createMock(ConfessionTracker::class);
        $tracker->method('isFindingAbsolved')->willReturn(false);
        $tracker->method('isFindingReported')->willReturn(false);

        $locations = (new Pilgrimage)->scanProphet(new LongMethodProphet, [$this->file], new CodebaseIndex, dirname($this->file), $tracker);

        $this->assertCount(1, $locations);
    }
}
