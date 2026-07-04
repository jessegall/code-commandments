<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Cli;

use JesseGall\CodeCommandments\Cli\ConstraintsCommand;
use JesseGall\CodeCommandments\Cli\Input;
use JesseGall\CodeCommandments\Cli\PlanConstraints;
use JesseGall\CodeCommandments\PlanExecution;
use PHPUnit\Framework\TestCase;

final class ConstraintsCommandTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/cc-constraintscmd-' . uniqid('', true);
        mkdir($this->root . '/.commandments', 0777, true);
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->root));
    }

    private function exec(string $head, string ...$args): int
    {
        $command = new ConstraintsCommand(new CapturingHookIO(new FakeGit($this->root, head: $head)));

        ob_start();
        $code = $command->run(Input::of('constraints', $args));
        ob_get_clean();

        return $code;
    }

    private function store(): PlanConstraints
    {
        return PlanConstraints::inWorktree($this->root, new PlanExecution);
    }

    public function test_add_records_a_local_rule_and_list_runs(): void
    {
        $this->assertSame(0, $this->exec('sha1', 'add', 'No frontend logic.'));
        $this->assertSame(['No frontend logic.'], $this->store()->local());
        $this->assertSame(0, $this->exec('sha1', 'list'));
    }

    public function test_add_joins_unquoted_words(): void
    {
        $this->exec('sha1', 'add', 'no', 'raw', 'sql');

        $this->assertSame(['no raw sql'], $this->store()->local());
    }

    public function test_add_without_a_rule_is_a_usage_error(): void
    {
        $this->assertSame(2, $this->exec('sha1', 'add'));
    }

    public function test_check_runs_when_constraints_exist(): void
    {
        $this->store()->addLocal('No frontend logic.');

        $this->assertSame(0, $this->exec('sha1', 'check'));
    }

    public function test_verified_stamps_the_current_head(): void
    {
        $this->store()->addLocal('No frontend logic.');

        $this->assertSame(0, $this->exec('sha-final', 'verified'));
        $this->assertTrue($this->store()->isVerifiedAt('sha-final'));
        $this->assertFalse($this->store()->isVerifiedAt('sha-other'));
    }

    public function test_unknown_subcommand_is_a_usage_error(): void
    {
        $this->assertSame(2, $this->exec('sha1', 'bogus'));
    }
}
