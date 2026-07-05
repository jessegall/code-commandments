<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Cli;

use JesseGall\CodeCommandments\Cli\Plan\PlanTesting;
use JesseGall\CodeCommandments\PlanExecution;
use PHPUnit\Framework\TestCase;

final class PlanTestingTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/cc-testing-' . uniqid('', true);
        mkdir($this->root . '/.commandments', 0777, true);
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->root));
    }

    private function testing(PlanExecution $plan): PlanTesting
    {
        return PlanTesting::inWorktree($this->root, $plan->build());
    }

    public function test_effective_falls_back_to_the_configured_default(): void
    {
        $t = $this->testing(new PlanExecution()->testFlow('Global: tests each phase.'));

        $this->assertSame('', $t->chosen(), 'nothing chosen for the run yet');
        $this->assertSame('Global: tests each phase.', $t->effective(), 'the config default stands in');
    }

    public function test_the_run_choice_overrides_the_default(): void
    {
        $t = $this->testing(new PlanExecution()->testFlow('Global default.'));

        $t->set('Run choice: only fix broken tests.');

        $this->assertSame('Run choice: only fix broken tests.', $t->chosen());
        $this->assertSame('Run choice: only fix broken tests.', $t->effective());
    }

    public function test_a_blank_choice_clears_back_to_the_default(): void
    {
        $t = $this->testing(new PlanExecution()->testFlow('Global default.'));
        $t->set('Run choice.');

        $t->set('   ');

        $this->assertSame('', $t->chosen());
        $this->assertSame('Global default.', $t->effective());
    }

    public function test_clear_drops_the_run_choice_but_not_the_default(): void
    {
        $t = $this->testing(new PlanExecution()->testFlow('kept default'));
        $t->set('dropped run choice');

        $t->clear();

        $this->assertSame('', $t->chosen());
        $this->assertSame('kept default', $t->effective(), 'the default comes from config, not the file');
    }

    public function test_effective_is_empty_when_nothing_is_configured_or_chosen(): void
    {
        $this->assertSame('', $this->testing(new PlanExecution)->effective());
    }
}
