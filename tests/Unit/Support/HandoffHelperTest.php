<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Support;

use JesseGall\CodeCommandments\Support\HandoffHelper;
use JesseGall\CodeCommandments\Tests\TestCase;

class HandoffHelperTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir() . '/cc-handoff-' . uniqid();
        mkdir($this->dir, 0755, true);
    }

    protected function tearDown(): void
    {
        shell_exec('rm -rf ' . escapeshellarg($this->dir));
        parent::tearDown();
    }

    public function test_install_writes_the_executable_helper(): void
    {
        $this->assertSame(HandoffHelper::STATUS_INSTALLED, HandoffHelper::install($this->dir));

        $path = $this->dir . '/.claude/hooks/' . HandoffHelper::SCRIPT;
        $this->assertFileExists($path);
        $this->assertTrue(is_executable($path));
        $this->assertStringStartsWith('#!', (string) file_get_contents($path));
    }

    public function test_install_is_idempotent(): void
    {
        $this->assertSame(HandoffHelper::STATUS_INSTALLED, HandoffHelper::install($this->dir));
        $this->assertSame(HandoffHelper::STATUS_INSTALLED, HandoffHelper::install($this->dir));
    }

    public function test_generated_handoff_has_snapshot_and_todo_template(): void
    {
        // Run the installed helper in a throwaway git repo and assert the doc.
        HandoffHelper::install($this->dir);
        $repo = $this->dir . '/repo';
        mkdir($repo, 0755, true);
        shell_exec('cd ' . escapeshellarg($repo) . ' && git init -q && git config user.email t@t && git config user.name t && echo x > a.txt && git add a.txt && git commit -qm "feat: thing"');
        copy($this->dir . '/.claude/hooks/' . HandoffHelper::SCRIPT, $repo . '/handoff.sh');
        shell_exec('cd ' . escapeshellarg($repo) . ' && sh handoff.sh >/dev/null 2>&1');

        $doc = (string) @file_get_contents($repo . '/HANDOFF.md');
        $this->assertStringContainsString('## Snapshot (auto-gathered)', $doc);
        $this->assertStringContainsString('feat: thing', $doc);          // auto-gathered commit
        $this->assertStringContainsString('>>> TODO', $doc);             // narrative template
        $this->assertStringContainsString('## Next step', $doc);
    }
}
