<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Prophets\Backend;

use JesseGall\CodeCommandments\Prophets\Backend\PreferFirstClassCallableProphet;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Tests\TestCase;

class PreferFirstClassCallableProphetTest extends TestCase
{
    private PreferFirstClassCallableProphet $prophet;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prophet = new PreferFirstClassCallableProphet;
    }

    public function test_flags_static_method_forward(): void
    {
        $j = $this->judge('$x->map(static fn (mixed $row) => Spec::forArray($row));');

        $this->assertCount(1, $j->warnings);
        $this->assertStringContainsString('Spec::forArray(...)', $j->warnings[0]->message);
    }

    public function test_flags_function_forward(): void
    {
        $j = $this->judge('$x->map(fn ($s) => trim($s));');

        $this->assertCount(1, $j->warnings);
        $this->assertStringContainsString('trim(...)', $j->warnings[0]->message);
    }

    public function test_flags_the_reported_static_typed_variable_receiver_case(): void
    {
        // #128 — the static modifier + typed param/return + a VARIABLE receiver
        // ($resource->m, not $this->m) must still be flagged with the right callable.
        $j = $this->judge('$items->transform(static fn (mixed $instance): string => $resource->describeInstance($instance));');

        $this->assertCount(1, $j->warnings);
        $this->assertStringContainsString('$resource->describeInstance(...)', $j->warnings[0]->message);
    }

    public function test_findings_stay_advisory_never_auto_fixable(): void
    {
        // #128 — this prophet deliberately does NOT auto-fix: the closure -> first-
        // class-callable swap can change behaviour when the higher-order caller
        // passes extra args the target would now receive (e.g. Collection::map's
        // $key), an arity check that needs the target's signature. So its findings
        // must NEVER carry autoFixable === true, and it must not be a SinRepenter —
        // otherwise `judge --next` prints a [AUTO-FIXABLE] tag + "run repent" that
        // no repenter backs (the bug reported against a stale consumer version).
        $j = $this->judge('$x->map(fn ($s) => trim($s));');

        $this->assertNotEmpty($j->warnings);
        $this->assertNotSame(true, $j->warnings[0]->autoFixable, 'PreferFirstClassCallable findings must stay advisory.');
        $this->assertNotInstanceOf(\JesseGall\CodeCommandments\Contracts\SinRepenter::class, $this->prophet);
    }

    public function test_flags_instance_method_forward(): void
    {
        $j = $this->judge('$x->each(fn ($u) => $this->render($u));');

        $this->assertCount(1, $j->warnings);
        $this->assertStringContainsString('$this->render(...)', $j->warnings[0]->message);
    }

    public function test_flags_single_return_closure(): void
    {
        $j = $this->judge('$x->map(function ($s) { return strtoupper($s); });');

        $this->assertCount(1, $j->warnings);
        $this->assertStringContainsString('strtoupper(...)', $j->warnings[0]->message);
    }

    public function test_ignores_added_argument(): void
    {
        $this->assertTrue($this->judge('$x->map(fn ($s) => trim($s, "/"));')->isRighteous());
    }

    public function test_ignores_wrapped_result(): void
    {
        $this->assertTrue($this->judge('$x->map(fn ($s) => strlen($s) + 1);')->isRighteous());
        $this->assertTrue($this->judge('$x->map(fn ($s) => g(f($s)));')->isRighteous());
    }

    public function test_ignores_arg_reshaping(): void
    {
        $this->assertTrue($this->judge('$x->map(fn ($a, $b) => f($b, $a));')->isRighteous());
        $this->assertTrue($this->judge('$x->map(fn ($u) => f($u->id));')->isRighteous());
    }

    public function test_ignores_zero_param_thunk(): void
    {
        $this->assertTrue($this->judge('Option::when($c, fn () => build());')->isRighteous());
    }

    public function test_ignores_nullsafe_call(): void
    {
        $this->assertTrue($this->judge('$x->map(fn ($u) => $u?->name());')->isRighteous());
    }

    private function judge(string $body): Judgment
    {
        return $this->prophet->judge('/x.php', "<?php\n" . $body);
    }
}
