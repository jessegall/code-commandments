<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Support;

use JesseGall\CodeCommandments\Support\PlanLoopHookSuite;
use JesseGall\CodeCommandments\Tests\TestCase;

class PlanLoopHookSuiteTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir() . '/cc-planloop-' . uniqid();
        mkdir($this->dir, 0755, true);
    }

    protected function tearDown(): void
    {
        shell_exec('rm -rf ' . escapeshellarg($this->dir));
        parent::tearDown();
    }

    public function test_enabled_reads_the_config_flag(): void
    {
        $this->assertFalse(PlanLoopHookSuite::enabled([]));
        $this->assertFalse(PlanLoopHookSuite::enabled(['hooks' => []]));
        $this->assertFalse(PlanLoopHookSuite::enabled(['hooks' => ['plan_loop' => false]]));
        $this->assertTrue(PlanLoopHookSuite::enabled(['hooks' => ['plan_loop' => true]]));
    }

    public function test_install_copies_every_script_executable(): void
    {
        $status = PlanLoopHookSuite::install($this->dir);

        $this->assertSame(PlanLoopHookSuite::STATUS_INSTALLED, $status);

        foreach (PlanLoopHookSuite::SCRIPTS as $script) {
            $path = $this->dir . '/.claude/hooks/' . $script;
            $this->assertFileExists($path, "missing {$script}");
            $this->assertTrue(is_executable($path), "{$script} not executable");
            $this->assertStringStartsWith('#!', (string) file_get_contents($path));
        }
    }

    public function test_install_is_idempotent(): void
    {
        $this->assertSame(PlanLoopHookSuite::STATUS_INSTALLED, PlanLoopHookSuite::install($this->dir));
        $this->assertSame(PlanLoopHookSuite::STATUS_INSTALLED, PlanLoopHookSuite::install($this->dir));
    }

    public function test_settings_entries_reference_the_scripts(): void
    {
        $this->assertSame('sh .claude/hooks/guard-plan-marker.sh', PlanLoopHookSuite::preToolUseEntries()[0]['hooks'][0]['command']);
        $this->assertSame('sh .claude/hooks/keep-going.sh', PlanLoopHookSuite::stopEntry()['hooks'][0]['command']);

        $post = PlanLoopHookSuite::postToolUseEntries();
        $this->assertSame(['ExitPlanMode', 'Bash'], array_map(static fn (array $e): string => $e['matcher'], $post));
        $this->assertSame('sh .claude/hooks/plan-approved.sh', $post[0]['hooks'][0]['command']);
        $this->assertSame('sh .claude/hooks/phase-committed.sh', $post[1]['hooks'][0]['command']);
    }

    public function test_every_referenced_script_is_in_the_suite(): void
    {
        // The four wired commands must each name a script the installer ships.
        $wired = [
            PlanLoopHookSuite::preToolUseEntries()[0]['hooks'][0]['command'],
            PlanLoopHookSuite::stopEntry()['hooks'][0]['command'],
            ...array_map(static fn (array $e): string => $e['hooks'][0]['command'], PlanLoopHookSuite::postToolUseEntries()),
        ];

        foreach ($wired as $command) {
            $script = basename((string) preg_replace('/^sh \.claude\/hooks\//', '', $command));
            $this->assertContains($script, PlanLoopHookSuite::SCRIPTS, "{$command} points at an unshipped script");
        }
    }
}
