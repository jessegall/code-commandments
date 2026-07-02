<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Cli;

use JesseGall\CodeCommandments\Cli\Checks;
use JesseGall\CodeCommandments\Moment;
use JesseGall\CodeCommandments\PlanExecution;
use PHPUnit\Framework\TestCase;

final class ChecksTest extends TestCase
{
    public function test_complete_appends_judge_against_the_base_branch(): void
    {
        $plan = new PlanExecution()->branchFrom('develop')->onComplete('composer test', 'composer lint');

        $this->assertSame(
            ['composer test', 'composer lint', 'vendor/bin/commandments judge --branch=develop'],
            new Checks()->commands(Moment::Complete, $plan),
        );
    }

    public function test_complete_appends_judge_even_with_no_declared_checks(): void
    {
        $this->assertSame(
            ['vendor/bin/commandments judge --branch=main'],
            new Checks()->commands(Moment::Complete, new PlanExecution),
        );
    }

    public function test_start_and_phase_do_not_append_judge(): void
    {
        $plan = new PlanExecution()->onStart('composer install')->eachPhase('composer lint');

        $this->assertSame(['composer install'], new Checks()->commands(Moment::Start, $plan));
        $this->assertSame(['composer lint'], new Checks()->commands(Moment::Phase, $plan));
    }

    public function test_empty_start_and_phase_are_empty(): void
    {
        $this->assertSame([], new Checks()->commands(Moment::Start, new PlanExecution));
        $this->assertSame([], new Checks()->commands(Moment::Phase, new PlanExecution));
    }
}
