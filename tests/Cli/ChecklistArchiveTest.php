<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Cli;

use JesseGall\CodeCommandments\Cli\Judge;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * {@see Judge} rotates the checklist archives — a re-run stamps the old checklist aside, but only
 * the newest handful are kept so `.commandments/` never grows one file per run forever.
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

        $prune = new ReflectionMethod(Judge::class, 'pruneArchives');
        $prune->invoke(new Judge(), $stem, 'md');

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
}
