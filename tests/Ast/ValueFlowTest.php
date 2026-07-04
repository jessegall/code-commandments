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
}
