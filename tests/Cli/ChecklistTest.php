<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Cli;

use JesseGall\CodeCommandments\Cli\Checklist;
use PHPUnit\Framework\TestCase;

final class ChecklistTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/cc-checklist-' . uniqid('', true);
        mkdir($this->dir . '/.commandments', 0777, true);
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->dir));
    }

    public function test_counts_remaining_sin_lines(): void
    {
        file_put_contents($this->live(), "# header\n\n## backend/absence\n\n- `a.php:1`  A::m  [X]\n- `b.php:2`  B::n  [Y]\n");

        $this->assertSame(2, Checklist::inProject($this->dir)->remainingSins());
    }

    public function test_a_missing_or_worked_off_worklist_has_no_remaining_sins(): void
    {
        $this->assertSame(0, Checklist::inProject($this->dir)->remainingSins(), 'no file');

        file_put_contents($this->live(), "# header\n\nAll clear.\n");
        $this->assertSame(0, Checklist::inProject($this->dir)->remainingSins(), 'no sin lines left');
    }

    public function test_fingerprint_changes_with_content_and_is_null_when_absent(): void
    {
        $this->assertNull(Checklist::inProject($this->dir)->fingerprint());

        file_put_contents($this->live(), "one\n");
        $a = Checklist::inProject($this->dir)->fingerprint();

        file_put_contents($this->live(), "two\n");
        $b = Checklist::inProject($this->dir)->fingerprint();

        $this->assertNotNull($a);
        $this->assertNotSame($a, $b);
    }

    public function test_clear_all_removes_the_live_file_and_its_archives(): void
    {
        file_put_contents($this->live(), "live\n");
        file_put_contents($this->dir . '/.commandments/sins-2026-07-04_101112.md', "old\n");
        file_put_contents($this->dir . '/.commandments/sins-2026-07-03_090807.md', "older\n");

        Checklist::inProject($this->dir)->clearAll();

        $this->assertFileDoesNotExist($this->live());
        $this->assertCount(0, glob($this->dir . '/.commandments/sins*.md') ?: [], 'archives are gone too');
    }

    private function live(): string
    {
        return $this->dir . '/.commandments/sins.md';
    }
}
