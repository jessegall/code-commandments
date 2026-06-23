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

    public function test_refresh_existing_overwrites_present_scripts_only(): void
    {
        $hooks = $this->dir . '/.claude/hooks';
        mkdir($hooks, 0755, true);
        // One stale suite script present; the rest absent.
        file_put_contents($hooks . '/plan-start.sh', "#!/usr/bin/env sh\n# STALE keep-going.sh reference\n");

        $count = PlanLoopHookSuite::refreshExisting($this->dir);

        $this->assertSame(1, $count, 'only the present script is refreshed');
        // The present one now matches the shipped stub (no stale keep-going ref)…
        $this->assertStringNotContainsString('STALE', (string) file_get_contents($hooks . '/plan-start.sh'));
        // …and no NEW suite script was added.
        $this->assertFileDoesNotExist($hooks . '/guard-plan-marker.sh');
    }

    public function test_settings_entries_reference_the_scripts(): void
    {
        $this->assertSame('sh .claude/hooks/plan-session-reset.sh', PlanLoopHookSuite::sessionStartEntries()[0]['hooks'][0]['command']);
        $this->assertSame('sh .claude/hooks/guard-plan-marker.sh', PlanLoopHookSuite::preToolUseEntries()[0]['hooks'][0]['command']);
        // The Stop driver is no longer the suite's own hook — it is the active
        // profile's stop-hook.sh (which reads the plan-active marker the suite arms).

        $post = PlanLoopHookSuite::postToolUseEntries();
        $this->assertSame(['ExitPlanMode', 'Bash'], array_map(static fn (array $e): string => $e['matcher'], $post));
        $this->assertSame('sh .claude/hooks/plan-approved.sh', $post[0]['hooks'][0]['command']);
        $this->assertSame('sh .claude/hooks/phase-committed.sh', $post[1]['hooks'][0]['command']);
    }

    public function test_every_referenced_script_is_in_the_suite(): void
    {
        // Every wired command must name a script the installer ships.
        $wired = [
            PlanLoopHookSuite::sessionStartEntries()[0]['hooks'][0]['command'],
            PlanLoopHookSuite::preToolUseEntries()[0]['hooks'][0]['command'],
            ...array_map(static fn (array $e): string => $e['hooks'][0]['command'], PlanLoopHookSuite::postToolUseEntries()),
        ];

        foreach ($wired as $command) {
            $script = basename((string) preg_replace('/^sh \.claude\/hooks\//', '', $command));
            $this->assertContains($script, PlanLoopHookSuite::SCRIPTS, "{$command} points at an unshipped script");
        }
    }

    public function test_session_reset_clears_marker_only_on_a_new_session(): void
    {
        $dir = sys_get_temp_dir() . '/cc-planreset-' . uniqid();
        mkdir($dir, 0755, true);
        shell_exec('cd ' . escapeshellarg($dir) . ' && git init -q');
        PlanLoopHookSuite::install($dir);
        $script = $dir . '/.claude/hooks/plan-session-reset.sh';
        $marker = $dir . '/.claude/plan-active';

        $run = function (string $source) use ($script, $dir, $marker): bool {
            file_put_contents($marker, '0');
            shell_exec('cd ' . escapeshellarg($dir) . ' && printf ' . escapeshellarg('{"source":"' . $source . '"}') . ' | sh ' . escapeshellarg($script));

            return file_exists($marker);
        };

        // A brand-new session clears the stale marker…
        $this->assertFalse($run('startup'), 'startup must clear the marker');
        // …but a compaction / resume keeps an in-flight plan alive.
        $this->assertTrue($run('compact'), 'compact must keep the marker');
        $this->assertTrue($run('resume'), 'resume must keep the marker');

        shell_exec('rm -rf ' . escapeshellarg($dir));
    }
}
