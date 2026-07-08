<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\Backend\Spatie;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Detectors\Backend\Spatie\PageObjectMissingTypeScriptDetector;
use PHPUnit\Framework\TestCase;

final class PageObjectMissingTypeScriptDetectorTest extends TestCase
{
    private const string PRELUDE = <<<'PHP'
        <?php
        namespace App;
        use Illuminate\Routing\Controller;
        use Spatie\LaravelData\Data;
        use Spatie\TypeScriptTransformer\Attributes\TypeScript;
        class Header extends Data { public function __construct(public readonly string $title = '') {} }
        class Sidebar extends Data { public function __construct(public readonly string $nav = '') {} }
        PHP;

    public function test_flags_a_response_bound_page_object_without_typescript(): void
    {
        $this->assertSame(1, $this->hits(<<<'PHP'
        final class Dashboard extends Data {
            public function __construct(
                public readonly Header $header,
                public readonly Sidebar $sidebar,
            ) {}
        }
        class C extends Controller { public function a(): Dashboard { return Dashboard::from([]); } }
        PHP));
    }

    public function test_does_not_flag_a_page_object_that_has_typescript(): void
    {
        $this->assertSame(0, $this->hits(<<<'PHP'
        #[TypeScript]
        final class Dashboard extends Data {
            public function __construct(
                public readonly Header $header,
                public readonly Sidebar $sidebar,
            ) {}
        }
        class C extends Controller { public function a(): Dashboard { return Dashboard::from([]); } }
        PHP));
    }

    public function test_does_not_flag_a_leaf_data_that_is_never_response_bound(): void
    {
        // Header/Sidebar are nested leaves reached through the annotated page — never flagged themselves.
        $this->assertSame(0, $this->hits(<<<'PHP'
        #[TypeScript]
        final class Dashboard extends Data {
            public function __construct(
                public readonly Header $header,
                public readonly Sidebar $sidebar,
            ) {}
        }
        class C extends Controller { public function a(): Dashboard { return Dashboard::from([]); } }
        PHP));
    }

    public function test_does_not_flag_a_single_data_composing_response(): void
    {
        // Composes only ONE nested Data — a plain DTO response, not a page object.
        $this->assertSame(0, $this->hits(<<<'PHP'
        final class Envelope extends Data {
            public function __construct(
                public readonly Header $header,
                public readonly string $note = '',
            ) {}
        }
        class C extends Controller { public function a(): Envelope { return Envelope::from([]); } }
        PHP));
    }

    public function test_does_not_flag_a_multi_data_object_that_never_reaches_a_response(): void
    {
        // Composes two nested Data but is never returned — an internal aggregate, not a page.
        $this->assertSame(0, $this->hits(<<<'PHP'
        final class Bundle extends Data {
            public function __construct(
                public readonly Header $header,
                public readonly Sidebar $sidebar,
            ) {}
        }
        PHP));
    }

    private function hits(string $body): int
    {
        return count((new PageObjectMissingTypeScriptDetector)->find(Codebase::fromString(self::PRELUDE . $body, '/proj/app/Page.php')));
    }
}
