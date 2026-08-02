<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\Backend\Spatie;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Detectors\Backend\Spatie\DataCollectionTypeDetector;
use JesseGall\CodeCommandments\Scribes\Backend\DataCollectionTypeScribe;
use PHPUnit\Framework\TestCase;

final class DataCollectionTypeDetectorTest extends TestCase
{
    private const string PRELUDE = <<<'PHP'
        <?php
        namespace App;
        use Spatie\LaravelData\Data;
        use Spatie\LaravelData\DataCollection;
        use Spatie\LaravelData\Attributes\DataCollectionOf;
        class NodeData extends Data { public function __construct(public string $id) {} }
        PHP;

    public function test_flags_a_property_typed_data_collection(): void
    {
        $this->assertSame(1, $this->hits(<<<'PHP'
        class Page extends Data {
            public function __construct(public readonly DataCollection $nodes) {}
        }
        PHP));
    }

    public function test_flags_a_nullable_data_collection(): void
    {
        $this->assertSame(1, $this->hits(<<<'PHP'
        class Page extends Data {
            public function __construct(public readonly DataCollection|null $nodes = null) {}
        }
        PHP));
    }

    public function test_does_not_flag_array_with_data_collection_of(): void
    {
        $this->assertSame(0, $this->hits(<<<'PHP'
        class Page extends Data {
            public function __construct(
                #[DataCollectionOf(NodeData::class)]
                public readonly array $nodes = [],
            ) {}
        }
        PHP));
    }

    public function test_does_not_flag_a_non_data_class(): void
    {
        $this->assertSame(0, $this->hits(<<<'PHP'
        class Page {
            public function __construct(public readonly DataCollection $nodes) {}
        }
        PHP));
    }

    public function test_the_scribe_retypes_to_array_keeping_an_existing_attribute(): void
    {
        $php = self::PRELUDE . <<<'PHP'
        class Page extends Data {
            public function __construct(
                #[DataCollectionOf(NodeData::class)]
                public readonly DataCollection $nodes,
            ) {}
        }
        PHP;

        $fixed = $this->fix($php);

        $this->assertStringContainsString('public readonly array $nodes', $fixed);
        $this->assertStringNotContainsString('DataCollection $nodes', $fixed);
        $this->assertStringContainsString('#[DataCollectionOf(NodeData::class)]', $fixed);
    }

    public function test_the_scribe_leaves_a_docblock_only_property_for_a_hand_fix(): void
    {
        // The element lives only in the `@var` docblock, not on the AST as `#[DataCollectionOf]`. Adding the
        // attribute would mean parsing the docblock by hand — forbidden — so the detector flags it and the
        // scribe leaves it untouched (still flagged; the human adds `#[DataCollectionOf]` + retypes).
        $php = self::PRELUDE . <<<'PHP'
        class Page extends Data {
            public function __construct(
                /** @var DataCollection<int, NodeData> */
                public readonly DataCollection $nodes,
            ) {}
        }
        PHP;

        $codebase = Codebase::fromString($php, '/proj/app/Page.php');
        $rewrites = new DataCollectionTypeScribe()->rewrite((new DataCollectionTypeDetector)->find($codebase));

        $this->assertSame([], $rewrites, 'a docblock-only element is left for a hand-fix, never scraped');
    }

    public function test_the_scribe_retypes_the_matching_param_docblock_and_drops_the_dead_import(): void
    {
        // #436: retyping the signature alone left the file saying two things at once — a `@param
        // DataCollection<…>` contradicting the real `array`, and an import nothing spelled any more.
        $php = self::PRELUDE . <<<'PHP'
        class Page extends Data {
            /**
             * @param  DataCollection<int, NodeData>  $nodes
             * @param  array<string>  $icons
             */
            public function __construct(
                #[DataCollectionOf(NodeData::class)]
                public readonly DataCollection $nodes,
                public readonly DataCollection $rows,
                public readonly array $icons = [],
            ) {}
        }
        PHP;

        // The `$rows` param has no `#[DataCollectionOf]`, so it is left for a hand-fix — and while it
        // stands, the import is still spelled and must survive.
        $stillNamed = $this->fix($php);

        $this->assertStringContainsString('use Spatie\LaravelData\DataCollection;', $stillNamed);
        $this->assertStringContainsString('@param  array<int, NodeData>  $nodes', $stillNamed);

        $fixed = $this->fix(str_replace("        public readonly DataCollection \$rows,\n", '', $php));

        $this->assertStringContainsString('@param  array<int, NodeData>  $nodes', $fixed);
        $this->assertStringContainsString('@param  array<string>  $icons', $fixed);
        $this->assertStringNotContainsString('DataCollection<', $fixed);
        $this->assertStringNotContainsString('use Spatie\LaravelData\DataCollection;', $fixed);
        $this->assertStringContainsString('use Spatie\LaravelData\Data;', $fixed);
    }

    public function test_the_scribe_keeps_an_import_a_nested_docblock_type_still_spells(): void
    {
        // `array<string, DataCollection<string, X>>` is a map OF collections — the scribe never
        // retypes it, so the name is still written and the import is still owed.
        $php = self::PRELUDE . <<<'PHP'
        class Page extends Data {
            /**
             * @param  DataCollection<int, NodeData>  $nodes
             * @param  array<string, DataCollection<string, NodeData>>  $grouped
             */
            public function __construct(
                #[DataCollectionOf(NodeData::class)]
                public readonly DataCollection $nodes,
                public readonly array $grouped = [],
            ) {}
        }
        PHP;

        $fixed = $this->fix($php);

        $this->assertStringContainsString('@param  array<int, NodeData>  $nodes', $fixed);
        $this->assertStringContainsString('@param  array<string, DataCollection<string, NodeData>>  $grouped', $fixed);
        $this->assertStringContainsString('use Spatie\LaravelData\DataCollection;', $fixed);
    }

    private function hits(string $body): int
    {
        return count((new DataCollectionTypeDetector)->find(Codebase::fromString(self::PRELUDE . $body, '/proj/app/File.php')));
    }

    private function fix(string $php): string
    {
        $codebase = Codebase::fromString($php, '/proj/app/Page.php');
        $rewrites = new DataCollectionTypeScribe()->rewrite((new DataCollectionTypeDetector)->find($codebase));

        return reset($rewrites) ?: $php;
    }
}
