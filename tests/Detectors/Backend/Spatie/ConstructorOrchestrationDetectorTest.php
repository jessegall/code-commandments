<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\Backend\Spatie;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Detectors\Backend\Spatie\ConstructorOrchestrationDetector;
use PHPUnit\Framework\TestCase;

final class ConstructorOrchestrationDetectorTest extends TestCase
{
    /**
     * Modelled on the real EditorShell constructor: the pure `$this->x = $this->projector->…()` slots
     * flag; the Lazy slot, the shared-local slots, and the branch-guarded slot do NOT.
     */
    public function test_flags_only_the_self_contained_slot_fills(): void
    {
        $flagged = $this->find(<<<'PHP'
        <?php
        namespace App;
        use Illuminate\Routing\Controller;
        use Spatie\LaravelData\Data;
        use Spatie\LaravelData\Lazy;
        class Canvas extends Data { public function __construct(public string $svg) {} }
        class Shell extends Data {
            public readonly Canvas $canvas;
            public readonly Canvas $palette;
            public readonly array $topBarCenter;   // pure -> flag
            public readonly array $docks;          // pure -> flag
            public readonly array $footer;         // reads a local -> reject
            public readonly array $stats;          // Lazy RHS -> reject
            public readonly Lazy|array $table;      // Lazy-typed slot -> reject
            public readonly string|null $realtime; // ternary of $this -> flag

            public function __construct(
                public readonly TopBar $topBar,
                public readonly Config $config,
                public readonly string $id,
            ) {
                $this->topBarCenter = $this->topBar->center();
                $this->docks = $this->topBar->docks();
                $menu = $this->topBar->menu($this->id);
                $this->footer = $this->topBar->footer($menu);
                $this->stats = Lazy::closure(fn () => $this->topBar->stats());
                $this->table = $this->topBar->asClosureLazy();
                $this->realtime = $this->config->enabled() ? $this->config->url() : null;
            }
        }
        class C extends Controller { public function a(): Shell { return Shell::from([]); } }
        PHP);

        $this->assertEqualsCanonicalizing(['topBarCenter', 'docks', 'realtime'], $flagged);
    }

    public function test_rejects_a_conditional_assignment(): void
    {
        // A slot filled inside an `if` is not a straight-line projection.
        $this->assertSame([], $this->find(<<<'PHP'
        <?php
        namespace App;
        use Illuminate\Routing\Controller;
        use Spatie\LaravelData\Data;
        class Canvas extends Data { public function __construct(public string $svg) {} }
        class Shell extends Data {
            public readonly Canvas $canvas;
            public readonly Canvas $palette;
            public readonly array $extra;
            public function __construct(public readonly Feature $feature) {
                if ($this->feature->on()) {
                    $this->extra = $this->feature->rows();
                }
            }
        }
        class C extends Controller { public function a(): Shell { return Shell::from([]); } }
        PHP));
    }

    public function test_rejects_a_slot_assigned_more_than_once(): void
    {
        $this->assertSame([], $this->find(<<<'PHP'
        <?php
        namespace App;
        use Illuminate\Routing\Controller;
        use Spatie\LaravelData\Data;
        class Canvas extends Data { public function __construct(public string $svg) {} }
        class Shell extends Data {
            public readonly Canvas $canvas;
            public readonly Canvas $palette;
            public readonly array $rows;
            public function __construct(public readonly Repo $repo) {
                $this->rows = $this->repo->base();
                $this->rows = $this->repo->filtered();
            }
        }
        class C extends Controller { public function a(): Shell { return Shell::from([]); } }
        PHP));
    }

    public function test_does_not_flag_a_private_scratch_assignment(): void
    {
        // A private property filled in the constructor is scratch state, not a serialized slot.
        $this->assertSame([], $this->find(<<<'PHP'
        <?php
        namespace App;
        use Illuminate\Routing\Controller;
        use Spatie\LaravelData\Data;
        class Canvas extends Data { public function __construct(public string $svg) {} }
        class Shell extends Data {
            public readonly Canvas $canvas;
            public readonly Canvas $palette;
            private readonly array $cache;
            public function __construct(public readonly Repo $repo) {
                $this->cache = $this->repo->warm();
            }
        }
        class C extends Controller { public function a(): Shell { return Shell::from([]); } }
        PHP));
    }

    public function test_does_not_flag_a_non_page_object(): void
    {
        $this->assertSame([], $this->find(<<<'PHP'
        <?php
        namespace App;
        use Spatie\LaravelData\Data;
        class Small extends Data {
            public readonly string $slug;
            public function __construct(public readonly Repo $repo) {
                $this->slug = $this->repo->slug();
            }
        }
        PHP));
    }

    /**
     * @return list<string>  the assigned property names that were flagged
     */
    private function find(string $php): array
    {
        $hits = (new ConstructorOrchestrationDetector)->find(Codebase::fromString($php, '/proj/app/Ui.php'));

        return array_map(static fn ($match): ?string => $match->assignedPropertyName(), $hits);
    }
}
