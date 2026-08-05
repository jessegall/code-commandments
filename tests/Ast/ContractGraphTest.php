<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Ast;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Ast\NodeMatch;
use PHPUnit\Framework\TestCase;

/**
 * "Does this type answer for that one" over the WHOLE contract graph — a class's own `implements`, the
 * parent chain, and the interfaces an interface EXTENDS (#448) — plus the selector that opens a query
 * over interface declarations (#447), which `whereClass` never matches.
 */
final class ContractGraphTest extends TestCase
{
    private const CODE = <<<'PHP'
        <?php
        namespace App;
        interface Bedrock {}
        interface ClientAction extends Bedrock {}
        interface Named {}
        class Base implements Named {}
        final class Mount implements ClientAction {}
        final class Child extends Base {}
        enum Kind: string implements ClientAction { case One = 'one'; }
        final class Loose {}
        PHP;

    public function test_a_class_reaches_a_marker_through_an_intermediate_interface(): void
    {
        // Marking a family with a base contract is how code says what it IS — the classification a
        // detector must see through, instead of matching on the name.
        $codebase = Codebase::fromString(self::CODE);

        $this->assertTrue($codebase->implements('App\Mount', 'App\ClientAction'), 'the contract it declares');
        $this->assertTrue($codebase->implements('App\Mount', 'App\Bedrock'), 'and the one that contract extends');
        $this->assertTrue($codebase->isA('App\Mount', 'App\Bedrock'));
        $this->assertFalse($codebase->implements('App\Loose', 'App\Bedrock'));
    }

    public function test_an_inherited_contract_still_answers(): void
    {
        $codebase = Codebase::fromString(self::CODE);

        $this->assertTrue($codebase->implements('App\Child', 'App\Named'), 'declared on the parent class');
    }

    public function test_an_enum_answers_for_the_contract_it_implements(): void
    {
        $codebase = Codebase::fromString(self::CODE);

        $this->assertTrue($codebase->implements('App\Kind', 'App\ClientAction'));
        $this->assertTrue($codebase->implements('App\Kind', 'App\Bedrock'), 'through the intermediate too');
    }

    public function test_an_interface_answers_for_what_it_extends(): void
    {
        $codebase = Codebase::fromString(self::CODE);

        $this->assertTrue($codebase->implements('App\ClientAction', 'App\Bedrock'));
    }

    public function test_the_interface_selector_opens_a_query_over_interface_declarations(): void
    {
        $names = array_map(
            static fn (NodeMatch $m): string => (string) ($m->node->namespacedName ?? ''),
            Codebase::fromString(self::CODE)->whereInterface()->get(),
        );

        $this->assertSame(['App\Bedrock', 'App\ClientAction', 'App\Named'], $names);
    }
}
