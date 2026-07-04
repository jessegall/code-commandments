<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\Backend\Spatie;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Detectors\Backend\Spatie\InjectedServiceNotHiddenDetector;
use PHPUnit\Framework\TestCase;

final class InjectedServiceNotHiddenDetectorTest extends TestCase
{
    public function test_flags_a_public_injected_service_without_hidden_on_a_page_object(): void
    {
        $flagged = $this->find(<<<'PHP'
        <?php
        namespace App;
        use Illuminate\Routing\Controller;
        use Spatie\LaravelData\Attributes\FromContainer;
        use Spatie\LaravelData\Attributes\Hidden;
        use Spatie\LaravelData\Data;

        class Canvas extends Data { public function __construct(public string $svg) {} }

        class ShellPage extends Data {
            public readonly Canvas $canvas;
            public readonly Canvas $palette;
            public function __construct(
                #[FromContainer(Normalizer::class)]
                public readonly Normalizer $normalizer,   // leaks — public, injected, not hidden
            ) {}
        }

        class GoodPage extends Data {
            public readonly Canvas $canvas;
            public readonly Canvas $palette;
            public function __construct(
                #[Hidden] #[FromContainer(Normalizer::class)]
                public readonly Normalizer $normalizer,   // hidden — righteous
            ) {}
        }

        class ShellController extends Controller {
            public function a(): ShellPage { return ShellPage::from([]); }
            public function b(): GoodPage { return GoodPage::from([]); }
        }
        PHP);

        $this->assertSame(['App\\ShellPage'], $flagged);
    }

    public function test_does_not_flag_a_private_injected_service(): void
    {
        // A non-public injected collaborator never serialized, so there is nothing to hide.
        $this->assertSame([], $this->find(<<<'PHP'
        <?php
        namespace App;
        use Illuminate\Routing\Controller;
        use Spatie\LaravelData\Attributes\FromContainer;
        use Spatie\LaravelData\Data;
        class Canvas extends Data { public function __construct(public string $svg) {} }
        class PrivatePage extends Data {
            public readonly Canvas $canvas;
            public readonly Canvas $palette;
            #[FromContainer(Normalizer::class)]
            private readonly Normalizer $normalizer;
        }
        class C extends Controller { public function a(): PrivatePage { return PrivatePage::from([]); } }
        PHP));
    }

    public function test_does_not_flag_a_non_page_object_data(): void
    {
        // Same un-hidden injection, but the Data composes no nested Data and is not returned — not a
        // page object, so this detector stays out of it.
        $this->assertSame([], $this->find(<<<'PHP'
        <?php
        namespace App;
        use Spatie\LaravelData\Attributes\FromContainer;
        use Spatie\LaravelData\Data;
        class SmallDto extends Data {
            public function __construct(
                #[FromContainer(Normalizer::class)]
                public readonly Normalizer $normalizer,
                public readonly string $token,
            ) {}
        }
        PHP));
    }

    public function test_does_not_flag_a_container_injected_data_payload(): void
    {
        // `#[FromContainer]` of a nested Data is legitimate PAYLOAD (built via the container, meant to
        // serialize) — not a service to hide. Only non-Data collaborators leak.
        $this->assertSame([], $this->find(<<<'PHP'
        <?php
        namespace App;
        use Illuminate\Routing\Controller;
        use Spatie\LaravelData\Attributes\FromContainer;
        use Spatie\LaravelData\Data;
        class Canvas extends Data { public function __construct(public string $svg) {} }
        class Flags extends Data { public function __construct(public bool $beta) {} }
        class FlagPage extends Data {
            public readonly Canvas $canvas;
            public readonly Canvas $palette;
            public function __construct(
                #[FromContainer(Flags::class)]
                public readonly Flags $flags,
            ) {}
        }
        class C extends Controller { public function a(): FlagPage { return FlagPage::from([]); } }
        PHP));
    }

    /**
     * @return list<string>  the flagged classes' scopes
     */
    private function find(string $php): array
    {
        $hits = (new InjectedServiceNotHiddenDetector)->find(Codebase::fromString($php, '/proj/app/Ui.php'));

        return array_map(static fn ($match): ?string => $match->enclosingClassName(), $hits);
    }
}
