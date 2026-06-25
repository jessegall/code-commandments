<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Ast;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Ast\NodeMatch;
use PHPUnit\Framework\TestCase;

final class CodebaseQueryTest extends TestCase
{
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
}
