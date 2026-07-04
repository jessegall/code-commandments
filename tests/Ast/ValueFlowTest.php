<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Ast;

use JesseGall\CodeCommandments\Ast\Codebase;
use PHPUnit\Framework\TestCase;

/**
 * {@see \JesseGall\CodeCommandments\Ast\ValueFlow} follows a field's value forward and reports how it
 * is consumed. Phase 1: the field's own reads and the locals they are assigned to (intra-procedural).
 */
final class ValueFlowTest extends TestCase
{
    /** The fixed value type + holder; each test supplies one reader body. */
    private function verdict(string $reader): array
    {
        $php = <<<PHP
        <?php
        namespace App;
        class Inner { public function v(): int { return 1; } }
        class Holder { public function __construct(public readonly ?Inner \$inner = null) {} }
        class Reader { {$reader} }
        PHP;

        $v = Codebase::fromString($php)->valueFlow()->verdict('App\\Holder', 'inner');

        return ['assume' => $v->assume, 'guard' => $v->guard];
    }

    public function test_counts_an_unguarded_dereference_as_assume(): void
    {
        $this->assertSame(['assume' => 1, 'guard' => 0], $this->verdict(
            'public function go(Holder $h): int { return $h->inner->v(); }',
        ));
    }

    public function test_counts_a_null_check_as_guard(): void
    {
        $this->assertSame(0, $this->verdict(
            'public function go(Holder $h): bool { return $h->inner !== null; }',
        )['assume']);
    }

    public function test_counts_a_truthiness_test_as_guard(): void
    {
        $v = $this->verdict('public function go(Holder $h): int { return $h->inner ? $h->inner->v() : 0; }');

        $this->assertGreaterThanOrEqual(1, $v['guard'], 'the ternary condition is a guard');
    }

    public function test_follows_an_assignment_to_a_local_then_dereference(): void
    {
        // $h->inner flows into $x, which is dereferenced un-guarded → assume via the assignment edge.
        $this->assertSame(['assume' => 1, 'guard' => 0], $this->verdict(
            'public function go(Holder $h): int { $x = $h->inner; return $x->v(); }',
        ));
    }

    public function test_follows_an_assignment_then_a_guard(): void
    {
        // $h->inner flows into $x, which is nullsafe-guarded → the downstream guard is seen.
        $v = $this->verdict('public function go(Holder $h): ?int { $x = $h->inner; return $x?->v(); }');

        $this->assertSame(0, $v['assume']);
        $this->assertGreaterThanOrEqual(1, $v['guard']);
    }

    public function test_a_field_with_no_reads_is_neutral(): void
    {
        $this->assertSame(['assume' => 0, 'guard' => 0], $this->verdict('public function go(): int { return 1; }'));
    }

    /** Inner + Holder plus arbitrary extra classes; verdict for Holder::$inner. */
    private function verdictWith(string $classes): array
    {
        $php = <<<PHP
        <?php
        namespace App;
        class Inner { public function v(): int { return 1; } }
        class Holder { public function __construct(public readonly ?Inner \$inner = null) {} }
        {$classes}
        PHP;

        $v = Codebase::fromString($php)->valueFlow()->verdict('App\\Holder', 'inner');

        return ['assume' => $v->assume, 'guard' => $v->guard];
    }

    public function test_follows_an_argument_into_a_nullable_parameter_then_dereference(): void
    {
        // $h->inner flows into consume()'s nullable $i, dereferenced there → assume, two hops deep.
        $this->assertSame(['assume' => 1, 'guard' => 0], $this->verdictWith(<<<'PHP'
        class Reader {
            public function go(Holder $h): int { return $this->consume($h->inner); }
            private function consume(?Inner $i): int { return $i->v(); }
        }
        PHP));
    }

    public function test_a_downstream_guard_in_the_callee_clears_it(): void
    {
        // The ContextInput.options shape: the value flows onward and is guarded DOWNSTREAM.
        $v = $this->verdictWith(<<<'PHP'
        class Reader {
            public function go(Holder $h): ?int { return $this->consume($h->inner); }
            private function consume(?Inner $i): ?int { return $i?->v(); }
        }
        PHP);

        $this->assertSame(0, $v['assume'], 'the downstream nullsafe guard means it is NOT phantom');
        $this->assertGreaterThanOrEqual(1, $v['guard']);
    }

    public function test_a_non_nullable_parameter_sink_is_a_terminal_assume(): void
    {
        // Passed into a non-nullable parameter — the signature demands presence; do not follow past it.
        $this->assertSame(['assume' => 1, 'guard' => 0], $this->verdictWith(<<<'PHP'
        class Reader {
            public function go(Holder $h): void { $this->consume($h->inner); }
            private function consume(Inner $i): void {}
        }
        PHP));
    }

    public function test_follows_the_value_into_another_objects_field_and_finds_its_guard(): void
    {
        // $h->inner is passed into Wrapper's promoted ?Inner field; Wrapper guards it → cleared.
        $v = $this->verdictWith(<<<'PHP'
        class Wrapper {
            public function __construct(public readonly ?Inner $inner = null) {}
            public function safe(): bool { return $this->inner !== null; }
        }
        class Reader {
            public function go(Holder $h): Wrapper { return new Wrapper(inner: $h->inner); }
        }
        PHP);

        $this->assertSame(0, $v['assume'], 'guarded two hops downstream in Wrapper');
        $this->assertGreaterThanOrEqual(1, $v['guard']);
    }

    public function test_follows_a_return_out_to_the_call_site(): void
    {
        // The field is returned by a getter, and the getter's result is dereferenced at the call site.
        $this->assertSame(['assume' => 1, 'guard' => 0], $this->verdictWith(<<<'PHP'
        class Box {
            public function __construct(private readonly Holder $h) {}
            public function getInner(): ?Inner { return $this->h->inner; }
            public function use(): int { return $this->getInner()->v(); }
        }
        PHP));
    }
}
