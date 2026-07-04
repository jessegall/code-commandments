<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\Backend\Spatie;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Detectors\Backend\Spatie\ServiceLocationInPageObjectDetector;
use PHPUnit\Framework\TestCase;

final class ServiceLocationInPageObjectDetectorTest extends TestCase
{
    public function test_flags_app_and_resolve_inside_a_page_object(): void
    {
        $scopes = $this->find(<<<'PHP'
        <?php
        namespace App;
        use Illuminate\Routing\Controller;
        use Spatie\LaravelData\Attributes\Computed;
        use Spatie\LaravelData\Data;
        class Canvas extends Data { public function __construct(public string $svg) {} }
        class EditorPage extends Data {
            public readonly Canvas $canvas;
            public readonly Canvas $palette;
            #[Computed]
            public bool $aiEnabled { get => app(AiService::class)->isEnabled(); }
            public function registry(): array { return resolve(NodeRegistry::class)->all(); }
        }
        class C extends Controller { public function a(): EditorPage { return EditorPage::from([]); } }
        PHP);

        // The hook-based reach scopes to the class (a property hook is not a ClassMethod); the method
        // reach scopes to Class::method. Both are flagged.
        $this->assertSame(['App\\EditorPage', 'App\\EditorPage::registry'], $scopes);
    }

    public function test_does_not_flag_container_injection(): void
    {
        // The blessed shape — the collaborator comes in #[FromContainer], no app() reach.
        $this->assertSame([], $this->find(<<<'PHP'
        <?php
        namespace App;
        use Illuminate\Routing\Controller;
        use Spatie\LaravelData\Attributes\FromContainer;
        use Spatie\LaravelData\Attributes\Hidden;
        use Spatie\LaravelData\Data;
        class Canvas extends Data { public function __construct(public string $svg) {} }
        class GoodPage extends Data {
            public readonly Canvas $canvas;
            public readonly Canvas $palette;
            public function __construct(
                #[Hidden] #[FromContainer(AiService::class)]
                public readonly AiService $ai,
            ) {}
            public function enabled(): bool { return $this->ai->isEnabled(); }
        }
        class C extends Controller { public function a(): GoodPage { return GoodPage::from([]); } }
        PHP));
    }

    public function test_does_not_flag_app_in_a_non_page_object(): void
    {
        // A plain service using app() is a different sin (container-reach), not a page-object concern.
        $this->assertSame([], $this->find(<<<'PHP'
        <?php
        namespace App;
        class SalesService {
            public function report(): string { return app(Clock::class)->now(); }
        }
        PHP));
    }

    public function test_does_not_flag_a_runtime_class_string(): void
    {
        // app($dynamic) resolves a type unknown until runtime — #[FromContainer] cannot replace it.
        $this->assertSame([], $this->find(<<<'PHP'
        <?php
        namespace App;
        use Illuminate\Routing\Controller;
        use Spatie\LaravelData\Data;
        class Canvas extends Data { public function __construct(public string $svg) {} }
        class DynPage extends Data {
            public readonly Canvas $canvas;
            public readonly Canvas $palette;
            public function make(string $type): object { return app($type); }
        }
        class C extends Controller { public function a(): DynPage { return DynPage::from([]); } }
        PHP));
    }

    /**
     * @return list<string>  the flagged findings' scopes (Class::method)
     */
    private function find(string $php): array
    {
        $hits = (new ServiceLocationInPageObjectDetector)->find(Codebase::fromString($php, '/proj/app/Ui.php'));

        return array_map(static fn ($match): string => $match->scope(), $hits);
    }
}
