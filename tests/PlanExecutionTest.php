<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests;

use JesseGall\CodeCommandments\Config;
use JesseGall\CodeCommandments\Moment;
use JesseGall\CodeCommandments\PlanExecution;
use JesseGall\CodeCommandments\PlanMode;
use JesseGall\CodeCommandments\PlanProfile;
use JesseGall\CodeCommandments\StopPolicy;
use PHPUnit\Framework\TestCase;

final class PlanExecutionTest extends TestCase
{
    public function test_build_freezes_into_a_read_profile(): void
    {
        $this->assertInstanceOf(PlanProfile::class, new PlanExecution()->build());
    }

    public function test_defaults_are_conservative(): void
    {
        $plan = new PlanExecution()->build();

        $this->assertSame('main', $plan->baseBranch());
        $this->assertSame('plan/', $plan->prefix());
        $this->assertFalse($plan->isEachPhasePushed(), 'push once at the end by default');
        $this->assertNull($plan->mode(), 'a plan mode is opt-in — unmanaged by default');
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
        $this->assertSame($plan, $plan->constraint('x'));
        $this->assertSame($plan, $plan->enforceConstraintsEachPhase());
        $this->assertSame($plan, $plan->testFlow('write tests each phase'));
        $this->assertSame($plan, $plan->trackWorkingState());
    }

    public function test_working_state_tracking_is_off_by_default_and_opt_in(): void
    {
        $this->assertFalse(new PlanExecution()->build()->isWorkingStateTracked(), 'off by default');
        $this->assertTrue(new PlanExecution()->trackWorkingState()->build()->isWorkingStateTracked());
        $this->assertFalse(new PlanExecution()->trackWorkingState(false)->build()->isWorkingStateTracked());
    }

    public function test_test_flow_defaults_empty_and_is_carried_into_the_profile(): void
    {
        $this->assertSame('', new PlanExecution()->build()->testFlow(), 'no default methodology by default');
        $this->assertSame(
            'Write and run the tests for each phase.',
            new PlanExecution()->testFlow('Write and run the tests for each phase.')->build()->testFlow(),
        );
    }

    public function test_check_buckets_accumulate_per_moment(): void
    {
        $plan = new PlanExecution()
            ->onStart('composer install')
            ->eachPhase('composer lint', 'composer types')
            ->onComplete('composer test')
            ->build();

        $this->assertSame(['composer install'], $plan->checksFor(Moment::Start));
        $this->assertSame(['composer lint', 'composer types'], $plan->checksFor(Moment::Phase));
        $this->assertSame(['composer test'], $plan->checksFor(Moment::Complete));
    }

    public function test_mode_records_the_chosen_posture(): void
    {
        $this->assertSame(PlanMode::Relentless, new PlanExecution()->mode(PlanMode::Relentless)->build()->mode());
        $this->assertSame(PlanMode::BestEffort, new PlanExecution()->mode(PlanMode::BestEffort)->build()->mode());
    }

    public function test_keep_going_maps_onto_the_mode_for_back_compat(): void
    {
        $this->assertSame(PlanMode::Autonomous, new PlanExecution()->keepGoing()->build()->mode());
        $this->assertSame(
            PlanMode::Supervised,
            new PlanExecution()->keepGoing(StopPolicy::RespectUserStops)->build()->mode(),
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
                ->mode(PlanMode::Autonomous)
                ->pushEachPhase()
                ->onComplete('composer test'))
            ->planExecutionSettings();

        $this->assertSame(PlanMode::Autonomous, $plan->mode());
        $this->assertTrue($plan->isEachPhasePushed());
        $this->assertSame(['composer test'], $plan->checksFor(Moment::Complete));
    }

    public function test_config_without_a_profile_yields_defaults(): void
    {
        $plan = new Config()->planExecutionSettings();

        $this->assertNull($plan->mode());
        $this->assertSame([], $plan->checksFor(Moment::Complete));
    }

    public function test_constraints_accumulate_and_are_off_by_default(): void
    {
        $default = new PlanExecution()->build();
        $this->assertSame([], $default->constraints());
        $this->assertFalse($default->enforcesConstraintsEachPhase(), 'phase enforcement is opt-in');

        $plan = new PlanExecution()
            ->constraint('Frontend is presentation-only.')
            ->constraint('No new global facades.', 'No raw SQL in controllers.')
            ->enforceConstraintsEachPhase()
            ->build();

        $this->assertSame(
            ['Frontend is presentation-only.', 'No new global facades.', 'No raw SQL in controllers.'],
            $plan->constraints(),
        );
        $this->assertTrue($plan->enforcesConstraintsEachPhase());
    }
}
