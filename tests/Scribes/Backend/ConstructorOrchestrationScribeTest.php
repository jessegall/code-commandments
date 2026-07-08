<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Scribes\Backend;

use JesseGall\CodeCommandments\Backend\Detector;
use JesseGall\CodeCommandments\Detectors\Backend\Spatie\ConstructorOrchestrationDetector;
use JesseGall\CodeCommandments\Scribes\Backend\ConstructorOrchestrationScribe;
use JesseGall\CodeCommandments\Scribes\RepentScribe;

final class ConstructorOrchestrationScribeTest extends ScribeTestCase
{
    protected function detector(): Detector
    {
        return new ConstructorOrchestrationDetector();
    }

    protected function scribe(): RepentScribe
    {
        return new ConstructorOrchestrationScribe();
    }

    public function test_hoists_slot_fills_into_get_hooks(): void
    {
        $fixed = $this->fixStable(<<<'PHP'
        <?php
        namespace App;
        use Illuminate\Routing\Controller;
        use Spatie\LaravelData\Data;
        class Canvas extends Data { public function __construct(public string $svg) {} }
        class Shell extends Data {
            public readonly Canvas $canvas;
            public readonly Canvas $palette;
            public readonly array $docks;

            public function __construct(
                public readonly TopBar $topBar,
            ) {
                $this->docks = $this->topBar->docks();
            }
        }
        class C extends Controller { public function a(): Shell { return Shell::from([]); } }
        PHP);

        $this->assertStringContainsString('public array $docks { get => $this->topBar->docks(); }', $fixed);
        $this->assertStringNotContainsString('readonly array $docks', $fixed);
        $this->assertStringNotContainsString('$this->docks =', $fixed);

        // The hook is a get-only virtual property — it MUST be `#[Computed]` or Spatie treats it as a
        // hydration input and the class won't build. The attribute needs its import to resolve.
        $this->assertStringContainsString("#[Computed]\n    public array \$docks", $fixed);
        $this->assertStringContainsString('use Spatie\LaravelData\Attributes\Computed;', $fixed);
    }

    public function test_does_not_duplicate_an_existing_computed_import(): void
    {
        $fixed = $this->fixStable(<<<'PHP'
        <?php
        namespace App;
        use Illuminate\Routing\Controller;
        use Spatie\LaravelData\Attributes\Computed;
        use Spatie\LaravelData\Data;
        class Canvas extends Data { public function __construct(public string $svg) {} }
        class Shell extends Data {
            public readonly Canvas $canvas;
            public readonly Canvas $palette;
            public readonly array $docks;

            public function __construct(public readonly TopBar $topBar) {
                $this->docks = $this->topBar->docks();
            }
        }
        class C extends Controller { public function a(): Shell { return Shell::from([]); } }
        PHP);

        $this->assertSame(1, substr_count($fixed, 'use Spatie\LaravelData\Attributes\Computed;'), 'the import is added at most once');
        $this->assertStringContainsString('#[Computed]', $fixed);
    }

    public function test_preserves_a_data_collection_of_attribute(): void
    {
        $fixed = $this->fixStable(<<<'PHP'
        <?php
        namespace App;
        use Illuminate\Routing\Controller;
        use Spatie\LaravelData\Attributes\DataCollectionOf;
        use Spatie\LaravelData\Data;
        class Row extends Data { public function __construct(public string $id) {} }
        class Canvas extends Data { public function __construct(public string $svg) {} }
        class Grid extends Data {
            public readonly Canvas $canvas;

            /** @var list<Row> */
            #[DataCollectionOf(Row::class)]
            public readonly array $rows;

            public function __construct(
                public readonly Repo $repo,
            ) {
                $this->rows = $this->repo->rows();
            }
        }
        class C extends Controller { public function a(): Grid { return Grid::from([]); } }
        PHP);

        // The attribute survives above the new `#[Computed]`; the slot becomes a hook.
        $this->assertStringContainsString("#[DataCollectionOf(Row::class)]\n    #[Computed]\n    public array \$rows", $fixed);
        $this->assertStringContainsString('public array $rows { get => $this->repo->rows(); }', $fixed);
    }

    public function test_does_not_overshoot_a_lazy_slot(): void
    {
        // A Lazy-typed slot is a righteous look-alike — the scribe must leave the file byte-identical.
        $source = <<<'PHP'
        <?php
        namespace App;
        use Illuminate\Routing\Controller;
        use Spatie\LaravelData\Data;
        use Spatie\LaravelData\Lazy;
        class Canvas extends Data { public function __construct(public string $svg) {} }
        class Shell extends Data {
            public readonly Canvas $canvas;
            public readonly Canvas $palette;
            public readonly Lazy|array $rows;

            public function __construct(
                public readonly Repo $repo,
            ) {
                $this->rows = Lazy::closure(fn () => $this->repo->rows());
            }
        }
        class C extends Controller { public function a(): Shell { return Shell::from([]); } }
        PHP;

        $this->assertFalse($this->rewrote($source));
    }
}
