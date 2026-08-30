<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Cli;

use JesseGall\CodeCommandments\Cli\Judge\Checklist;
use PHPUnit\Framework\TestCase;

/**
 * {@see Checklist} rotates its archives — a re-run stamps the old checklist aside, but only the
 * newest handful are kept so the folder never grows one file per run forever.
 */
final class ChecklistArchiveTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/cc-archive-' . uniqid('', true);
        mkdir($this->dir, 0777, true);
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->dir));
    }

    public function test_keeps_only_the_five_most_recent_archives(): void
    {
        $stem = $this->dir . '/sins';

        // Eight archives, oldest → newest by mtime.
        for ($i = 1; $i <= 8; $i++) {
            $file = "{$stem}-2026-01-0{$i}_000000.md";
            file_put_contents($file, "run {$i}");
            touch($file, 1_000_000 + $i * 100);
        }

        new Checklist("{$stem}.md")->pruneArchives();

        $remaining = array_map('basename', glob("{$stem}-*.md") ?: []);
        sort($remaining);

        $this->assertSame([
            'sins-2026-01-04_000000.md',
            'sins-2026-01-05_000000.md',
            'sins-2026-01-06_000000.md',
            'sins-2026-01-07_000000.md',
            'sins-2026-01-08_000000.md',
        ], $remaining, 'the five newest survive; the three oldest are pruned');
    }

    public function test_a_re_run_stamps_the_previous_checklist_aside_rather_than_clobbering_it(): void
    {
        $live = $this->dir . '/sins.md';

        file_put_contents($live, 'the run you were working through');
        touch($live, 1_767_225_600); // 2026-01-01 00:00:00 UTC

        new Checklist($live)->archive();

        $this->assertFileDoesNotExist($live, 'the live file moved out of the way');

        $archives = glob($this->dir . '/sins-*.md') ?: [];

        $this->assertCount(1, $archives);
        $this->assertSame('the run you were working through', file_get_contents($archives[0]));
    }
}
