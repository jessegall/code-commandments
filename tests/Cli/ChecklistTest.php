<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Cli;

use JesseGall\CodeCommandments\Cli\Judge\Checklist;
use JesseGall\CodeCommandments\Workspace;
use PHPUnit\Framework\TestCase;

final class ChecklistTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/cc-checklist-' . uniqid('', true);
        mkdir(Workspace::at($this->dir)->sessionDir(), 0777, true);
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->dir));
    }

    public function test_counts_remaining_sin_lines(): void
    {
        $this->writeLive("# header\n\n## backend/absence\n\n- `a.php:1`  A::m  [X]\n- `b.php:2`  B::n  [Y]\n");

        $this->assertSame(2, Checklist::inSession(Workspace::at($this->dir))->remainingSins());
    }

    public function test_a_missing_or_worked_off_worklist_has_no_remaining_sins(): void
    {
        $this->assertSame(0, Checklist::inSession(Workspace::at($this->dir))->remainingSins(), 'no file');

        $this->writeLive("# header\n\nAll clear.\n");
        $this->assertSame(0, Checklist::inSession(Workspace::at($this->dir))->remainingSins(), 'no sin lines left');
    }

    public function test_fingerprint_changes_with_content_and_is_null_when_absent(): void
    {
        $this->assertNull(Checklist::inSession(Workspace::at($this->dir))->fingerprint());

        $this->writeLive("one\n");
        $a = Checklist::inSession(Workspace::at($this->dir))->fingerprint();

        $this->writeLive("two\n");
        $b = Checklist::inSession(Workspace::at($this->dir))->fingerprint();

        $this->assertNotNull($a);
        $this->assertNotSame($a, $b);
    }

    public function test_clear_all_removes_the_live_file_and_its_archives(): void
    {
        $this->writeLive("live\n");
        file_put_contents(Workspace::at($this->dir)->checklistArchive('2026-07-04_101112'), "old\n");
        file_put_contents(Workspace::at($this->dir)->checklistArchive('2026-07-03_090807'), "older\n");

        Checklist::inSession(Workspace::at($this->dir))->clearAll();

        $this->assertFileDoesNotExist($this->live());
        $this->assertCount(0, glob(Workspace::at($this->dir)->checklistDir() . '/sins*.md') ?: [], 'archives are gone too');
        $this->assertDirectoryDoesNotExist(Workspace::at($this->dir)->checklistDir(), 'the emptied folder goes with them');
    }

    public function test_the_checklist_lives_in_a_folder_of_its_own_inside_the_session(): void
    {
        $workspace = Workspace::at($this->dir);

        $this->assertSame($workspace->sessionDir() . '/sins/sins.md', $workspace->checklist());
        $this->assertSame($workspace->sessionDir() . '/sins/sins-2026-08-29_154514.md', $workspace->checklistArchive('2026-08-29_154514'));
        $this->assertSame($workspace->sessionDir() . '/sins', $workspace->checklistDir());
        $this->assertStringEndsWith('/sins/sins.md', $workspace->checklistRelative());
    }

    public function test_preparing_the_session_folder_creates_it_and_says_the_dated_files_are_kept(): void
    {
        $workspace = Workspace::at($this->dir);

        $this->assertTrue(Checklist::prepare($workspace->checklist(), $workspace), 'the folder was made');
        $this->assertDirectoryExists($workspace->checklistDir());

        $notes = (string) file_get_contents($workspace->checklistDir() . '/README.md');
        $this->assertStringContainsString('kept deliberately', $notes);
    }

    public function test_preparing_a_target_the_user_named_writes_no_note_beside_it(): void
    {
        $workspace = Workspace::at($this->dir);
        $mine = $this->dir . '/elsewhere/report.md';

        $this->assertTrue(Checklist::prepare($mine, $workspace));
        $this->assertDirectoryExists($this->dir . '/elsewhere');
        $this->assertFileDoesNotExist($this->dir . '/elsewhere/README.md', "a user's own folder is left alone");
    }

    /**
     * The live worklist's path. Pure on purpose — an earlier version made the folder here, and an
     * assertion that the folder is GONE recreated it on its way to reading the path.
     */
    private function live(): string
    {
        return Workspace::at($this->dir)->checklist();
    }

    private function writeLive(string $body): void
    {
        mkdir(Workspace::at($this->dir)->checklistDir(), 0777, true);
        file_put_contents($this->live(), $body);
    }
}
