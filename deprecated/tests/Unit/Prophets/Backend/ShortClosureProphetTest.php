<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Prophets\Backend;

use JesseGall\CodeCommandments\Prophets\Backend\ShortClosureProphet;
use JesseGall\CodeCommandments\Tests\TestCase;

class ShortClosureProphetTest extends TestCase
{
    private ShortClosureProphet $prophet;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prophet = new ShortClosureProphet();
    }

    private function judge(string $body): \JesseGall\CodeCommandments\Results\Judgment
    {
        return $this->prophet->judge('/x.php', "<?php\nnamespace App;\nclass C {\n{$body}\n}\n");
    }

    public function test_flags_a_big_closure(): void
    {
        $j = $this->judge('
            public function go($x) {
                return $x->map(function ($d) {
                    $a = $d->a();
                    $b = $d->b();
                    $c = $d->c();
                    $e = $d->e();
                    $f = $d->f();
                    $g = $d->g();
                    return $a + $b + $c + $e + $f + $g;
                });
            }
        ');

        $this->assertTrue($j->hasWarnings());
        $this->assertStringContainsString('named method in disguise', $j->warnings[0]->message);
    }

    public function test_does_not_flag_a_small_closure(): void
    {
        $j = $this->judge('public function go($x) { return $x->each(function ($v) use (&$a) { $a[] = $v; }); }');
        $this->assertFalse($j->hasWarnings());
    }

    public function test_a_single_multi_line_new_is_one_statement(): void
    {
        // Statements, not lines — a single `new X(...)` with named args is fine.
        $j = $this->judge('
            public function go($x) {
                return $x->each(function ($field) use (&$acc, $d) {
                    $acc[] = new Thing(
                        a: $d->a,
                        b: $field->name,
                        c: $field,
                        e: $d->e,
                    );
                });
            }
        ');

        $this->assertFalse($j->hasWarnings());
    }

    public function test_does_not_count_a_nested_closure_toward_the_outer(): void
    {
        // The outer closure has 3 statements; the nested one's body is not counted.
        $j = $this->judge('
            public function go($x) {
                return $x->map(function ($d) {
                    $a = $d->a();
                    $b = $d->b();
                    return $this->each(function ($v) { $x = 1; $y = 2; $z = 3; $w = 4; return $x; });
                });
            }
        ');

        $this->assertFalse($j->hasWarnings(), 'Outer closure is 3 statements; the nested closure is its own unit.');
    }

    public function test_exempts_arrow_functions(): void
    {
        $j = $this->judge('public function go($x) { return $x->map(fn ($d) => $d->a() + $d->b() + $d->c() + $d->e() + $d->f() + $d->g()); }');
        $this->assertFalse($j->hasWarnings());
    }

    public function test_respects_max_closure_statements_config(): void
    {
        $this->prophet->configure(['max_closure_statements' => 2]);
        $j = $this->judge('public function go($x) { return $x->map(function ($d) { $a = 1; $b = 2; $c = 3; return $a; }); }');
        $this->assertTrue($j->hasWarnings());
    }
}
