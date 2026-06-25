<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Unit\Prophets\Backend;

use JesseGall\CodeCommandments\Prophets\Backend\NoOptionToNullProphet;
use JesseGall\CodeCommandments\Results\Judgment;
use JesseGall\CodeCommandments\Tests\TestCase;

class NoOptionToNullProphetTest extends TestCase
{
    private NoOptionToNullProphet $prophet;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prophet = new NoOptionToNullProphet;
    }

    public function test_flags_get_or_null(): void
    {
        $judgment = $this->judge(<<<'PHP'
        class Resolver
        {
            public function go($port): void
            {
                $input = $this->inputByName($port)->unwrapOr(null);

                if ($input?->socketType() === SocketType::Bag) {
                    // ...
                }
            }
        }
        PHP);

        $this->assertCount(1, $judgment->warnings);
        $this->assertStringContainsString('null default', $judgment->warnings[0]->message);
    }

    public function test_flags_null_laundered_through_a_variable(): void
    {
        // The dodge: wrap null in a local so it's not the literal arg.
        $judgment = $this->judge(<<<'PHP'
        class Resolver
        {
            public function go($port)
            {
                $default = null;
                $input = $this->inputByName($port)->unwrapOr($default);

                return $input?->type();
            }
        }
        PHP);

        $this->assertCount(1, $judgment->warnings);
    }

    public function test_flags_null_via_coalesce_and_ternary(): void
    {
        $coalesce = $this->judge(<<<'PHP'
        class A { public function go($p) { $v = $this->opt($p)->unwrapOr($x ?? null); return $v?->x(); } }
        PHP);
        $ternary = $this->judge(<<<'PHP'
        class B { public function go($p, $cond) { $v = $this->opt($p)->unwrapOr($cond ? $y : null); return $v?->x(); } }
        PHP);

        $this->assertCount(1, $coalesce->warnings);
        $this->assertCount(1, $ternary->warnings);
    }

    public function test_does_not_flag_a_variable_that_holds_a_real_value(): void
    {
        // $default is assigned a real value — unwrapOr($default) is a genuine fallback.
        $judgment = $this->judge(<<<'PHP'
        class Resolver
        {
            public function go($port)
            {
                $default = Input::empty();

                return $this->inputByName($port)->unwrapOr($default);
            }
        }
        PHP);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_does_not_flag_get_or_with_a_real_default(): void
    {
        $judgment = $this->judge(<<<'PHP'
        class Resolver
        {
            public function go($port)
            {
                return $this->inputByName($port)->unwrapOr(Input::empty());
            }
        }
        PHP);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_does_not_flag_get_or_throw_or_map_or_each(): void
    {
        $judgment = $this->judge(<<<'PHP'
        class Resolver
        {
            public function go($port): void
            {
                $a = $this->inputByName($port)->unwrap();
                $b = $this->inputByName($port)->map(fn ($i) => $i->socketType());
                $this->inputByName($port)->inspect(fn ($i) => $i->go());
            }
        }
        PHP);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_does_not_flag_carry_into_a_nullable_argument(): void
    {
        // #23: the value-or-null is handed to a (nullable) constructor arg —
        // a carry, not a null-check.
        $judgment = $this->judge(<<<'PHP'
        class Reflector
        {
            public function build($attr)
            {
                return new OutputSocket(
                    isVisibleRule: $this->normaliseVisibilityRule($attr)->unwrapOr(null),
                );
            }
        }
        PHP);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_does_not_flag_carry_via_return(): void
    {
        // The method's own contract is ?T — returning unwrapOr(null) is the boundary.
        $judgment = $this->judge(<<<'PHP'
        class C
        {
            public function elementType($attr): ?string
            {
                return $this->resolveElementType($attr)->unwrapOr(null);
            }
        }
        PHP);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_does_not_flag_resolver_factory_arrow(): void
    {
        // firstResultWins entries must return T|null (null = no match).
        $judgment = $this->judge(<<<'PHP'
        class C
        {
            public function resolvers()
            {
                return Resolver::firstResultWins(
                    IsX::make()->then(fn ($r) => $this->build($r)->unwrapOr(null)),
                );
            }
        }
        PHP);

        $this->assertTrue($judgment->isRighteous());
    }

    public function test_describes_itself(): void
    {
        $this->assertNotEmpty($this->prophet->description());
        $this->assertNotEmpty($this->prophet->detailedDescription());
        $this->assertNotNull($this->prophet->advisory());
    }

    private function judge(string $body): Judgment
    {
        return $this->prophet->judge('/x.php', "<?php\n" . $body);
    }

    // ── #68: guarded auto-fix of the unwrap-then-null-check sub-pattern ──

    public function test_repent_collapses_unwrap_then_null_check(): void
    {
        $src = "<?php\nclass C {\n public function m(\$opt): void {\n  \$x = \$opt->unwrapOr(null);\n  if (\$x !== null) { \$t = 1; }\n }\n public function n(\$opt): void {\n  \$y = \$opt->unwrapOr(null);\n  if (\$y === null) { return; }\n }\n}\n";

        $result = $this->prophet->repent('/x.php', $src);

        $this->assertTrue($result->absolved);
        $this->assertStringContainsString('if ($opt->isSome()) {', $result->newContent);
        $this->assertStringContainsString('if ($opt->isNone()) {', $result->newContent);
        $this->assertStringNotContainsString('unwrapOr(null)', $result->newContent);
        $this->assertStringNotContainsString('$x =', $result->newContent);
    }

    public function test_repent_leaves_a_value_used_beyond_the_null_test(): void
    {
        // The trap: the unwrapped value is ALSO passed on — dropping it would
        // lose the value. Must not auto-fix.
        $src = "<?php\nclass C {\n public function m(\$opt): void {\n  \$x = \$opt->unwrapOr(null);\n  if (\$x !== null) { \$t = 1; }\n  options(\$x);\n }\n}\n";

        $this->assertFalse($this->prophet->repent('/x.php', $src)->absolved);
    }

    public function test_repent_leaves_a_call_receiver(): void
    {
        // Inlining a CALL receiver would re-evaluate it — not safe to drop the local.
        $src = "<?php\nclass C {\n public function m(): void {\n  \$x = \$this->e->valuesFor(1)->unwrapOr(null);\n  if (\$x !== null) { \$t = 1; }\n }\n}\n";

        $this->assertFalse($this->prophet->repent('/x.php', $src)->absolved);
    }
}
