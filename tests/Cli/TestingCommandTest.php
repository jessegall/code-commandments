<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Cli;

use JesseGall\CodeCommandments\Cli\Plan\TestingCommand;
use JesseGall\CodeCommandments\Cli\Plan\PlanTesting;
use JesseGall\CodeCommandments\Cli\Input;
use JesseGall\CodeCommandments\PlanExecution;
use JesseGall\CodeCommandments\Workspace;
use PHPUnit\Framework\TestCase;

final class TestingCommandTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/cc-testingcmd-' . uniqid('', true);
        mkdir($this->root . '/.commandments', 0777, true);
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->root));
    }

    private function exec(string ...$args): int
    {
        $command = new TestingCommand(new CapturingHookIO(new FakeGit($this->root)));

        ob_start();
        $code = $command->run(Input::of('testing', $args));
        ob_get_clean();

        return $code;
    }

    private function store(): PlanTesting
    {
        return PlanTesting::inSession(Workspace::at($this->root), new PlanExecution()->build());
    }

    public function test_set_records_the_run_methodology_joining_unquoted_words(): void
    {
        $this->assertSame(0, $this->exec('set', 'only', 'fix', 'broken', 'tests'));
        $this->assertSame('only fix broken tests', $this->store()->chosen());
    }

    public function test_set_without_a_methodology_is_a_usage_error(): void
    {
        $this->assertSame(2, $this->exec('set'));
    }

    public function test_show_runs(): void
    {
        $this->store()->set('tests each phase');

        $this->assertSame(0, $this->exec('show'));
    }

    public function test_unknown_subcommand_is_a_usage_error(): void
    {
        $this->assertSame(2, $this->exec('bogus'));
    }
}
