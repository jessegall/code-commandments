<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests;

use JesseGall\CodeCommandments\Config;
use JesseGall\CodeCommandments\Moment;
use JesseGall\CodeCommandments\PlanExecution;
use JesseGall\CodeCommandments\StopPolicy;
use PHPUnit\Framework\TestCase;

final class PlanExecutionTest extends TestCase
{
    public function test_defaults_are_conservative(): void
    {
        $plan = new PlanExecution;

        $this->assertSame('main', $plan->baseBranch());
        $this->assertSame('plan/', $plan->prefix());
        $this->assertFalse($plan->pushesEachPhase(), 'push once at the end by default');
        $this->assertNull($plan->stopPolicy(), 'keep-going is opt-in — off by default');
        $this->assertSame([], $plan->checksFor(Moment::Complete));
    }

    public function test_each_setter_returns_self_so_it_chains(): void
    {
        $plan = new PlanExecution;

        $this->assertSame($plan, $plan->branchFrom('develop'));
        $this->assertSame($plan, $plan->branchPrefix('feat/'));
        $this->assertSame($plan, $plan->pushEachPhase());
        $this->assertSame($plan, $plan->keepGoing());
        $this->assertSame($plan, $plan->onStart('a'));
        $this->assertSame($plan, $plan->eachPhase('b'));
        $this->assertSame($plan, $plan->onComplete('c'));
    }

    public function test_check_buckets_accumulate_per_moment(): void
    {
        $plan = new PlanExecution;
        $plan->onStart('composer install')
            ->eachPhase('composer lint', 'composer types')
            ->onComplete('composer test');

        $this->assertSame(['composer install'], $plan->checksFor(Moment::Start));
        $this->assertSame(['composer lint', 'composer types'], $plan->checksFor(Moment::Phase));
        $this->assertSame(['composer test'], $plan->checksFor(Moment::Complete));
    }

    public function test_keep_going_records_its_policy(): void
    {
        $this->assertSame(StopPolicy::UntilComplete, new PlanExecution()->keepGoing()->stopPolicy());
        $this->assertSame(
            StopPolicy::RespectUserStops,
            new PlanExecution()->keepGoing(StopPolicy::RespectUserStops)->stopPolicy(),
        );
    }

    public function test_config_applies_a_block_closure(): void
    {
        $plan = new Config()
            ->planExecution(function (PlanExecution $plan): void {
                $plan->branchPrefix('wip/')->onComplete('composer test');
            })
            ->planExecutionSettings();

        $this->assertSame('wip/', $plan->prefix());
        $this->assertSame(['composer test'], $plan->checksFor(Moment::Complete));
    }

    public function test_config_applies_a_fluent_arrow_closure(): void
    {
        $plan = new Config()
            ->planExecution(fn (PlanExecution $plan) => $plan
                ->keepGoing(StopPolicy::UntilComplete)
                ->pushEachPhase()
                ->onComplete('composer test'))
            ->planExecutionSettings();

        $this->assertSame(StopPolicy::UntilComplete, $plan->stopPolicy());
        $this->assertTrue($plan->pushesEachPhase());
        $this->assertSame(['composer test'], $plan->checksFor(Moment::Complete));
    }

    public function test_config_without_a_profile_yields_defaults(): void
    {
        $plan = new Config()->planExecutionSettings();

        $this->assertNull($plan->stopPolicy());
        $this->assertSame([], $plan->checksFor(Moment::Complete));
    }
}
