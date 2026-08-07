<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Cli;

use JesseGall\CodeCommandments\Cli\Plan\Checks;
use JesseGall\CodeCommandments\Moment;
use JesseGall\CodeCommandments\PlanExecution;
use PHPUnit\Framework\TestCase;

final class ChecksTest extends TestCase
{
    /**
     * A project with nothing installed, so the checks below read the path a consumer gets. Where the
     * executable really is has its own test.
     */
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/cc-checks-' . uniqid('', true);
    }

    public function test_complete_appends_judge_against_the_base_branch(): void
    {
        $plan = new PlanExecution()->branchFrom('develop')->onComplete('composer test', 'composer lint')->build();

        $this->assertSame(
            ['composer test', 'composer lint', 'vendor/bin/commandments judge --branch=develop'],
            new Checks()->commands(Moment::Complete, $plan, $this->root),
        );
    }

    public function test_complete_appends_judge_even_with_no_declared_checks(): void
    {
        $this->assertSame(
            ['vendor/bin/commandments judge --branch=main'],
            new Checks()->commands(Moment::Complete, new PlanExecution()->build(), $this->root),
        );
    }

    public function test_start_and_phase_do_not_append_judge(): void
    {
        $plan = new PlanExecution()->onStart('composer install')->eachPhase('composer lint')->build();

        $this->assertSame(['composer install'], new Checks()->commands(Moment::Start, $plan, $this->root));
        $this->assertSame(['composer lint'], new Checks()->commands(Moment::Phase, $plan, $this->root));
    }

    public function test_empty_start_and_phase_are_empty(): void
    {
        $this->assertSame([], new Checks()->commands(Moment::Start, new PlanExecution()->build(), $this->root));
        $this->assertSame([], new Checks()->commands(Moment::Phase, new PlanExecution()->build(), $this->root));
    }

    public function test_complete_appends_the_constraint_check_when_constraints_exist(): void
    {
        $plan = new PlanExecution()->constraint('No frontend logic.')->build();

        $this->assertSame(
            ['vendor/bin/commandments judge --branch=main', 'vendor/bin/commandments constraints check'],
            new Checks()->commands(Moment::Complete, $plan, $this->root),
        );
    }

    public function test_phase_appends_the_constraint_check_only_when_enforced(): void
    {
        $soft = new PlanExecution()->constraint('No frontend logic.')->build();
        $this->assertSame([], new Checks()->commands(Moment::Phase, $soft, $this->root), 'phase is a nudge by default');

        $forced = new PlanExecution()->constraint('No frontend logic.')->enforceConstraintsEachPhase()->build();
        $this->assertSame(
            ['vendor/bin/commandments constraints check'],
            new Checks()->commands(Moment::Phase, $forced, $this->root),
        );
    }

    public function test_no_constraint_check_when_the_project_declares_none(): void
    {
        $this->assertSame(
            ['vendor/bin/commandments judge --branch=main'],
            new Checks()->commands(Moment::Complete, new PlanExecution()->build(), $this->root),
            'a project without constraints never sees the line',
        );
    }

    public function test_a_project_that_carries_its_own_binary_is_told_to_run_that_one(): void
    {
        // The package itself: composer never shims a package's own bin into its own vendor, so a
        // check naming `vendor/bin/commandments` was a gate that could not run where it matters most.
        @mkdir("{$this->root}/bin", 0777, true);
        touch("{$this->root}/bin/commandments");

        $this->assertSame(
            ['bin/commandments judge --branch=main'],
            new Checks()->commands(Moment::Complete, new PlanExecution()->build(), $this->root),
        );

        unlink("{$this->root}/bin/commandments");
        rmdir("{$this->root}/bin");
        rmdir($this->root);
    }
}
