<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Cli;

use JesseGall\CodeCommandments\Cli\Plan\PlanConstraints;
use JesseGall\CodeCommandments\PlanExecution;
use PHPUnit\Framework\TestCase;

final class PlanConstraintsTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/cc-constraints-' . uniqid('', true);
        mkdir($this->root . '/.commandments', 0777, true);
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->root));
    }

    private function constraints(PlanExecution $plan): PlanConstraints
    {
        return PlanConstraints::inWorktree($this->root, $plan->build());
    }

    public function test_active_is_global_then_local(): void
    {
        $plan = new PlanExecution()->constraint('Global: no frontend logic.');
        $c = $this->constraints($plan);

        $this->assertSame(['Global: no frontend logic.'], $c->active());

        $c->addLocal('Local: this run stays in the API layer.');

        $this->assertSame(
            ['Global: no frontend logic.', 'Local: this run stays in the API layer.'],
            $c->active(),
        );
        $this->assertSame(['Local: this run stays in the API layer.'], $c->local());
    }

    public function test_add_local_is_idempotent_and_ignores_blank(): void
    {
        $c = $this->constraints(new PlanExecution);

        $c->addLocal('Rule A');
        $c->addLocal('Rule A');
        $c->addLocal('   ');

        $this->assertSame(['Rule A'], $c->local());
    }

    public function test_verification_is_fresh_only_at_the_stamped_head(): void
    {
        $c = $this->constraints(new PlanExecution);

        $this->assertFalse($c->isVerifiedAt('abc123'), 'unverified by default');

        $c->markVerified('abc123');

        $this->assertTrue($c->isVerifiedAt('abc123'));
        $this->assertFalse($c->isVerifiedAt('def456'), 'a moved HEAD is stale');
        $this->assertFalse($c->isVerifiedAt(''), 'no-commits HEAD is never fresh');
    }

    public function test_clear_drops_local_and_verification(): void
    {
        $c = $this->constraints(new PlanExecution()->constraint('kept global'));
        $c->addLocal('dropped local');
        $c->markVerified('abc123');

        $c->clear();

        $this->assertSame([], $c->local());
        $this->assertFalse($c->isVerifiedAt('abc123'));
        $this->assertSame(['kept global'], $c->active(), 'global constraints come from config, not the file');
    }
}
