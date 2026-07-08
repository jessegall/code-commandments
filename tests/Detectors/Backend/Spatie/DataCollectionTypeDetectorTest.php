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
