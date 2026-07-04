<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Cli;

use JesseGall\CodeCommandments\Cli\Plan\Checks;
use JesseGall\CodeCommandments\Moment;
use JesseGall\CodeCommandments\PlanExecution;
use PHPUnit\Framework\TestCase;

final class ChecksTest extends TestCase
{
    public function test_complete_appends_judge_against_the_base_branch(): void
    {
        $plan = new PlanExecution()->branchFrom('develop')->onComplete('composer test', 'composer lint')->build();

        $this->assertSame(
            ['composer test', 'composer lint', 'vendor/bin/commandments judge --branch=develop'],
            new Checks()->commands(Moment::Complete, $plan),
        );
    }

    public function test_complete_appends_judge_even_with_no_declared_checks(): void
    {
        $this->assertSame(
            ['vendor/bin/commandments judge --branch=main'],
            new Checks()->commands(Moment::Complete, new PlanExecution()->build()),
        );
    }

    public function test_start_and_phase_do_not_append_judge(): void
    {
        $plan = new PlanExecution()->onStart('composer install')->eachPhase('composer lint')->build();

        $this->assertSame(['composer install'], new Checks()->commands(Moment::Start, $plan));
        $this->assertSame(['composer lint'], new Checks()->commands(Moment::Phase, $plan));
    }

    public function test_empty_start_and_phase_are_empty(): void
    {
        $this->assertSame([], new Checks()->commands(Moment::Start, new PlanExecution()->build()));
        $this->assertSame([], new Checks()->commands(Moment::Phase, new PlanExecution()->build()));
    }

    public function test_complete_appends_the_constraint_check_when_constraints_exist(): void
    {
        $plan = new PlanExecution()->constraint('No frontend logic.')->build();

        $this->assertSame(
            ['vendor/bin/commandments judge --branch=main', 'vendor/bin/commandments constraints check'],
            new Checks()->commands(Moment::Complete, $plan),
        );
    }

    public function test_phase_appends_the_constraint_check_only_when_enforced(): void
    {
        $soft = new PlanExecution()->constraint('No frontend logic.')->build();
        $this->assertSame([], new Checks()->commands(Moment::Phase, $soft), 'phase is a nudge by default');

        $forced = new PlanExecution()->constraint('No frontend logic.')->enforceConstraintsEachPhase()->build();
        $this->assertSame(
            ['vendor/bin/commandments constraints check'],
            new Checks()->commands(Moment::Phase, $forced),
        );
    }

    public function test_no_constraint_check_when_the_project_declares_none(): void
    {
        $this->assertSame(
            ['vendor/bin/commandments judge --branch=main'],
            new Checks()->commands(Moment::Complete, new PlanExecution()->build()),
            'a project without constraints never sees the line',
        );
    }
}
