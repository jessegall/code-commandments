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

    public function test_install_writes_the_resume_helper_too(): void
    {
        $this->assertSame(HandoffHelper::STATUS_INSTALLED, HandoffHelper::install($this->dir));

        $path = $this->dir . '/.claude/hooks/' . HandoffHelper::RESUME_SCRIPT;
        $this->assertFileExists($path);
        $this->assertTrue(is_executable($path));
        $this->assertStringStartsWith('#!', (string) file_get_contents($path));
    }

    public function test_resume_helper_assembles_a_briefing_from_handoff_and_live_state(): void
    {
        HandoffHelper::install($this->dir);
        $repo = $this->dir . '/repo';
        mkdir($repo, 0755, true);
        shell_exec('cd ' . escapeshellarg($repo) . ' && git init -q && git config user.email t@t && git config user.name t && echo x > a.txt && git add a.txt && git commit -qm "feat: groundwork"');
        copy($this->dir . '/.claude/hooks/' . HandoffHelper::RESUME_SCRIPT, $repo . '/resume.sh');

        // A prior handoff + a plan-progress memory, loop NOT armed.
        file_put_contents($repo . '/HANDOFF.md', "# Handoff\n## Next step\nWire the parser.\n");
        $mem = $this->dir . '/mem';
        mkdir($mem, 0755, true);
        file_put_contents($mem . '/feature-x-progress.md', "GOAL: ship feature X\n");

        $out = (string) shell_exec('cd ' . escapeshellarg($repo) . ' && CLAUDE_MEMORY_DIR=' . escapeshellarg($mem) . ' sh resume.sh 2>/dev/null');

        $this->assertStringContainsString('RESUME BRIEFING', $out);
        $this->assertStringContainsString('Wire the parser.', $out);          // the handoff
        $this->assertStringContainsString('LIVE REPO', $out);                  // live re-verify
        $this->assertStringContainsString('feat: groundwork', $out);          // live commits
        $this->assertStringContainsString('ship feature X', $out);            // plan memory
        $this->assertStringContainsString('ACTIVE TODO LIST', $out);          // next-steps guidance
        $this->assertStringContainsString('plan-start.sh', $out);             // re-arm guidance
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

    public function test_includes_the_plan_progress_memory_even_when_the_loop_is_inactive(): void
    {
        // The bug: agents stop the plan loop to write a handoff, so the .claude/
        // plan-active marker is gone — the plan must still be surfaced from its
        // progress memory file (which lives independently of the loop marker).
        HandoffHelper::install($this->dir);
        $repo = $this->dir . '/repo';
        mkdir($repo, 0755, true);
        shell_exec('cd ' . escapeshellarg($repo) . ' && git init -q && git config user.email t@t && git config user.name t && echo x > a.txt && git add a.txt && git commit -qm "feat: thing"');
        copy($this->dir . '/.claude/hooks/' . HandoffHelper::SCRIPT, $repo . '/handoff.sh');

        // A plan-progress memory file, but NO .claude/plan-active marker.
        $mem = $this->dir . '/mem';
        mkdir($mem, 0755, true);
        file_put_contents($mem . '/feature-x-progress.md', "GOAL: ship feature X\nNEXT STEP: write the parser\n");

        shell_exec('cd ' . escapeshellarg($repo) . ' && CLAUDE_MEMORY_DIR=' . escapeshellarg($mem) . ' sh handoff.sh >/dev/null 2>&1');

        $doc = (string) @file_get_contents($repo . '/HANDOFF.md');
        $this->assertStringContainsString('Plan loop active:** no', $doc);          // loop is off…
        $this->assertStringContainsString('feature-x-progress.md', $doc);           // …but the plan is still referenced
        $this->assertStringContainsString('Plan progress —', $doc);                 // and its contents included
        $this->assertStringContainsString('ship feature X', $doc);
    }

    public function test_notes_when_no_plan_progress_memory_exists(): void
    {
        HandoffHelper::install($this->dir);
        $repo = $this->dir . '/repo';
        mkdir($repo, 0755, true);
        shell_exec('cd ' . escapeshellarg($repo) . ' && git init -q && git config user.email t@t && git config user.name t && echo x > a.txt && git add a.txt && git commit -qm "feat: thing"');
        copy($this->dir . '/.claude/hooks/' . HandoffHelper::SCRIPT, $repo . '/handoff.sh');

        $mem = $this->dir . '/empty-mem';
        mkdir($mem, 0755, true);

        shell_exec('cd ' . escapeshellarg($repo) . ' && CLAUDE_MEMORY_DIR=' . escapeshellarg($mem) . ' sh handoff.sh >/dev/null 2>&1');

        $doc = (string) @file_get_contents($repo . '/HANDOFF.md');
        $this->assertStringContainsString('Plan progress memory:** none found', $doc);
    }
}
