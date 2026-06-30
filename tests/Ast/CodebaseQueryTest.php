<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Ast;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Ast\NodeMatch;
use PHPUnit\Framework\TestCase;

final class CodebaseQueryTest extends TestCase
{
    public function test_a_file_that_does_not_parse_is_skipped_not_thrown(): void
    {
        // A syntax error must not crash the scan — the file just contributes nothing.
        $codebase = Codebase::fromString('<?php class A { function f() { return * 5; } }', 'broken.php');

        $this->assertCount(0, $codebase->whereMethod('input')->get());
    }

    public function test_where_method_finds_calls_by_name(): void
    {
        $code = '<?php class A { function f($r) { $r->input("x"); $r->get("y"); $this->other(); } }';

        $hits = Codebase::fromString($code)->whereMethod('input', 'get')->get();

        $this->assertCount(2, $hits);
        $this->assertSame(['input', 'get'], array_map(static fn (NodeMatch $m): ?string => $m->callName(), $hits));
    }

    public function test_in_proximity_of(): void
    {
        $code = <<<'PHP'
        <?php
        function f($a) {
            $x = $a->get('one');



            if (is_null($z)) {}
            $y = $a->get('two');
        }
        PHP;

        $hits = Codebase::fromString($code)->whereMethod('get')->inProximityOf('is_null', 1)->get();

        $this->assertCount(1, $hits);
        $this->assertStringEndsWith(':8', $hits[0]->location());
    }

    public function test_is_used_on_keeps_outside_calls_and_drops_calls_inside_the_target(): void
    {
        $code = <<<'PHP'
        <?php
        namespace App;
        class Req {
            function selfUse() { $this->input('z'); }
        }
        class Outside {
            function b(\App\Req $r) { $r->input('y'); }
        }
        PHP;

        $hits = Codebase::fromString($code)->whereMethod('input')->isUsedOn('App\Req')->get();

        $this->assertCount(1, $hits);
        $this->assertSame('App\Outside', $hits[0]->enclosingClassName());
    }

    public function test_where_param_type_finds_typed_parameters_and_their_scope(): void
    {
        $code = '<?php namespace A; class S {} class C { public function __construct(private \A\S $s, int $n) {} public function m(\A\S $x) {} }';

        $all = Codebase::fromString($code)->whereParamType('A\S')->get();
        $this->assertCount(2, $all, 'the ctor param and the method param');

        $ctorOnly = Codebase::fromString($code)
            ->whereParamType('A\S')
            ->where(static fn (NodeMatch $m): bool => $m->enclosingFunctionName() === '__construct')
            ->get();
        $this->assertCount(1, $ctorOnly);
    }
}
