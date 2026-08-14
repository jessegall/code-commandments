<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Ast;

use JesseGall\CodeCommandments\Ast\Codebase;
use PHPUnit\Framework\TestCase;

/**
 * The `extends` graph a rewrite consults before it seals a class — the parent chain, who has a
 * subclass, and what an ancestor leaves mutable. One home for the walk, so no scribe re-derives it.
 */
final class InheritanceGraphTest extends TestCase
{
    public function test_the_parent_chain_reads_nearest_first(): void
    {
        $codebase = Codebase::fromString(<<<'PHP'
        <?php

        namespace App;

        class Base {}
        class Middle extends Base {}
        class Leaf extends Middle {}
        PHP);

        $this->assertSame(['App\Middle', 'App\Base'], $codebase->ancestorsOf('App\Leaf'));
        $this->assertSame([], $codebase->ancestorsOf('App\Base'));
        $this->assertTrue($codebase->extends('App\Leaf', 'App\Base'));
    }

    public function test_an_anonymous_subclass_still_makes_its_base_a_base(): void
    {
        $codebase = Codebase::fromString(<<<'PHP'
        <?php

        namespace App;

        class Contract {}

        function fake(): Contract
        {
            return new class extends Contract {};
        }
        PHP);

        $this->assertTrue($codebase->hasSubclass('App\Contract'), 'an anonymous class is a subclass too');
    }

    public function test_a_property_an_ancestor_leaves_mutable_is_reported_as_unsealable(): void
    {
        $codebase = Codebase::fromString(<<<'PHP'
        <?php

        namespace App;

        class Base
        {
            public function __construct(
                public string $id,
                public readonly string $slug,
            ) {}
        }

        class Child extends Base
        {
            public function __construct(
                public string $id,
                public readonly string $slug,
                public int $rank,
            ) {}
        }
        PHP);

        $this->assertTrue($codebase->inheritsMutableProperty('App\Child', 'id'));
        $this->assertFalse($codebase->inheritsMutableProperty('App\Child', 'slug'), 'the base already sealed it');
        $this->assertFalse($codebase->inheritsMutableProperty('App\Child', 'rank'), 'no ancestor declares it');
        $this->assertFalse($codebase->inheritsMutableProperty('App\Base', 'id'), 'a root has no ancestor to answer for');
    }

    public function test_a_plain_declared_property_counts_beside_a_promoted_one(): void
    {
        $codebase = Codebase::fromString(<<<'PHP'
        <?php

        namespace App;

        class Base
        {
            public bool $wasRecentlyCreated = false;
            public readonly string $token;
        }

        class Child extends Base {}
        PHP);

        $this->assertTrue($codebase->inheritsMutableProperty('App\Child', 'wasRecentlyCreated'));
        $this->assertFalse($codebase->inheritsMutableProperty('App\Child', 'token'));
    }
}
