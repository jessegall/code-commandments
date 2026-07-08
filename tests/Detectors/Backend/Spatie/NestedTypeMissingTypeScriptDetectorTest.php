<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\Backend\Spatie;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Detectors\Backend\Spatie\NestedTypeMissingTypeScriptDetector;
use PHPUnit\Framework\TestCase;

final class NestedTypeMissingTypeScriptDetectorTest extends TestCase
{
    private const string PRELUDE = <<<'PHP'
        <?php
        namespace App;
        use Spatie\LaravelData\Data;
        use Spatie\LaravelData\Optional;
        use Spatie\LaravelData\Attributes\DataCollectionOf;
        use Spatie\LaravelData\Attributes\Hidden;
        use Spatie\TypeScriptTransformer\Attributes\TypeScript;
        use Spatie\TypeScriptTransformer\Attributes\LiteralTypeScriptType;
        enum Status: string { case Draft = 'draft'; }
        #[TypeScript] enum TaggedStatus: string { case Open = 'open'; }
        final class Leaf extends Data { public function __construct(public readonly string $v = '') {} }
        #[TypeScript] final class TaggedLeaf extends Data { public function __construct(public readonly string $v = '') {} }
        PHP;

    public function test_flags_a_nested_untagged_data_property(): void
    {
        $this->assertSame(1, $this->hits(<<<'PHP'
        #[TypeScript]
        final class Page extends Data {
            public function __construct(
                public readonly string $id,
                public readonly Leaf $leaf,
            ) {}
        }
        PHP));
    }

    public function test_does_not_flag_a_nested_untagged_enum(): void
    {
        // The transformer's enum collector auto-generates a type for ANY enum, tagged or not — so an
        // untagged nested enum is never `undefined`. Not a hole.
        $this->assertSame(0, $this->hits(<<<'PHP'
        #[TypeScript]
        final class Page extends Data {
            public function __construct(
                public readonly string $id,
                public readonly Status $status,
            ) {}
        }
        PHP));
    }

    public function test_flags_a_datacollectionof_element_that_is_untagged(): void
    {
        $this->assertSame(1, $this->hits(<<<'PHP'
        #[TypeScript]
        final class Page extends Data {
            /** @var list<Leaf> */
            #[DataCollectionOf(Leaf::class)]
            public readonly array $leaves;
            public function __construct(public readonly string $id) {}
        }
        PHP));
    }

    public function test_flags_through_an_optional_or_nullable_wrapper(): void
    {
        $this->assertSame(2, $this->hits(<<<'PHP'
        #[TypeScript]
        final class Page extends Data {
            public function __construct(
                public readonly Leaf|Optional $a = new Optional(),
                public readonly ?Leaf $b = null,
            ) {}
        }
        PHP));
    }

    public function test_does_not_flag_when_the_nested_type_is_typescript(): void
    {
        $this->assertSame(0, $this->hits(<<<'PHP'
        #[TypeScript]
        final class Page extends Data {
            public function __construct(
                public readonly TaggedLeaf $leaf,
                public readonly TaggedStatus $status,
            ) {}
        }
        PHP));
    }

    public function test_does_not_flag_when_the_parent_is_not_typescript(): void
    {
        // The parent itself never generates, so the nested type's tagging is moot here.
        $this->assertSame(0, $this->hits(<<<'PHP'
        final class Page extends Data {
            public function __construct(public readonly Leaf $leaf) {}
        }
        PHP));
    }

    public function test_does_not_flag_a_hidden_property(): void
    {
        // Off the wire → off the generated type.
        $this->assertSame(0, $this->hits(<<<'PHP'
        #[TypeScript]
        final class Page extends Data {
            public function __construct(
                #[Hidden]
                public readonly Leaf $leaf,
                public readonly string $id = '',
            ) {}
        }
        PHP));
    }

    public function test_does_not_flag_a_property_with_a_literal_typescript_override(): void
    {
        $this->assertSame(0, $this->hits(<<<'PHP'
        #[TypeScript]
        final class Page extends Data {
            public function __construct(
                #[LiteralTypeScriptType('{ v: string }')]
                public readonly Leaf $leaf,
            ) {}
        }
        PHP));
    }

    public function test_does_not_flag_a_scalar_or_vendor_type(): void
    {
        // A scalar has no nested type; a type we can't see parsed (Carbon) is left alone.
        $this->assertSame(0, $this->hits(<<<'PHP'
        #[TypeScript]
        final class Page extends Data {
            public function __construct(
                public readonly string $id,
                public readonly int $n,
                public readonly \Carbon\Carbon $at,
            ) {}
        }
        PHP));
    }

    private function hits(string $body): int
    {
        return count((new NestedTypeMissingTypeScriptDetector)->find(Codebase::fromString(self::PRELUDE . $body, '/proj/app/Page.php')));
    }
}
