<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Cli;

use JesseGall\CodeCommandments\Cli\PlanCommand;
use JesseGall\CodeCommandments\Cli\PlanMarker;
use PHPUnit\Framework\TestCase;

final class PlanCommandTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/cc-plancmd-' . uniqid('', true);
        @mkdir($this->root . '/.commandments', 0777, true);
    }

    protected function tearDown(): void
    {
        @unlink($this->root . '/.commandments/.plan-active');
        @rmdir($this->root . '/.commandments');
        @rmdir($this->root);
    }

    public function test_done_clears_an_active_plan(): void
    {
        $marker = PlanMarker::inWorktree($this->root);
        $marker->activate('sha0');

        $this->assertSame(0, $this->exec('done'));
        $this->assertFalse($marker->isActive(), 'the keep-going marker is cleared');
    }

    public function test_done_is_a_no_op_without_an_active_plan(): void
    {
        $this->assertSame(0, $this->exec('done'));
        $this->assertFalse(PlanMarker::inWorktree($this->root)->isActive());
    }

    public function test_status_runs_in_both_states(): void
    {
        $this->assertSame(0, $this->exec('status'));

        PlanMarker::inWorktree($this->root)->activate('sha0');
        $this->assertSame(0, $this->exec('status'));
    }

    public function test_status_is_the_default(): void
    {
        $this->assertSame(0, $this->exec());
    }

    public function test_an_unknown_subcommand_is_a_usage_error(): void
    {
        $this->assertSame(2, $this->exec('bogus'));
    }

    private function exec(string ...$args): int
    {
        $command = new PlanCommand(new CapturingHookIO(new FakeGit($this->root)));

        ob_start();
        $code = $command->run($args);
        ob_get_clean();

        return $code;
    }
}
