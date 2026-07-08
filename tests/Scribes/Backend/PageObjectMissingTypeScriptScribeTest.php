<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Scribes\Backend;

use JesseGall\CodeCommandments\Backend\Detector;
use JesseGall\CodeCommandments\Detectors\Backend\Spatie\PageObjectMissingTypeScriptDetector;
use JesseGall\CodeCommandments\Scribes\Backend\PageObjectMissingTypeScriptScribe;
use JesseGall\CodeCommandments\Scribes\RepentScribe;

final class PageObjectMissingTypeScriptScribeTest extends ScribeTestCase
{
    protected function detector(): Detector
    {
        return new PageObjectMissingTypeScriptDetector();
    }

    protected function scribe(): RepentScribe
    {
        return new PageObjectMissingTypeScriptScribe();
    }

    public function test_stamps_typescript_and_imports_it_on_a_page_object(): void
    {
        $php = <<<'PHP'
        <?php

        namespace Spatie\LaravelData { class Data {} }
        namespace Illuminate\Routing { class Controller {} }

        namespace App {
            use Illuminate\Routing\Controller;
            use Spatie\LaravelData\Data;

            class Header extends Data { public function __construct(public string $title = '') {} }
            class Sidebar extends Data { public function __construct(public string $nav = '') {} }

            final class Dashboard extends Data
            {
                public function __construct(
                    public readonly Header $header,
                    public readonly Sidebar $sidebar,
                ) {}
            }

            class PageController extends Controller
            {
                public function show(): Dashboard { return Dashboard::from([]); }
            }
        }
        PHP;

        $fixed = $this->fixStable($php);

        $this->assertStringContainsString("#[TypeScript]\n    final class Dashboard extends Data", $fixed);
        $this->assertStringContainsString('use Spatie\TypeScriptTransformer\Attributes\TypeScript;', $fixed);
    }

    public function test_leaves_a_page_object_that_already_has_typescript(): void
    {
        $php = <<<'PHP'
        <?php

        namespace Spatie\LaravelData { class Data {} }
        namespace Illuminate\Routing { class Controller {} }
        namespace Spatie\TypeScriptTransformer\Attributes { #[\Attribute] class TypeScript {} }

        namespace App {
            use Illuminate\Routing\Controller;
            use Spatie\LaravelData\Data;
            use Spatie\TypeScriptTransformer\Attributes\TypeScript;

            class Header extends Data { public function __construct(public string $title = '') {} }
            class Sidebar extends Data { public function __construct(public string $nav = '') {} }

            #[TypeScript]
            final class Dashboard extends Data
            {
                public function __construct(
                    public readonly Header $header,
                    public readonly Sidebar $sidebar,
                ) {}
            }

            class PageController extends Controller
            {
                public function show(): Dashboard { return Dashboard::from([]); }
            }
        }
        PHP;

        $this->assertFalse($this->rewrote($php));
    }
}
