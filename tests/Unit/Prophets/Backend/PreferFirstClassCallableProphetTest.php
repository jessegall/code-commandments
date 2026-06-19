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
