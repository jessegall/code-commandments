<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\Backend\Spatie;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Detectors\Backend\Spatie\RedundantNestedFromDetector;
use PHPUnit\Framework\TestCase;

/**
 * A nested `X::from([...])` is redundant when the parent `::from` slot — a nested `Data` or a
 * `#[DataCollectionOf(X)]` element — auto-hydrates the array itself. Object sources, supertype slots,
 * `#[WithCast]` slots, and per-item maps (ManualHydrationLoop's turf) are spared.
 */
final class RedundantNestedFromDetectorTest extends TestCase
{
    private function hits(string $php): int
    {
        return count(new RedundantNestedFromDetector()->find(Codebase::fromString($php)));
    }

    private const HEADER = <<<'PHP'
        <?php
        namespace App;
        use Spatie\LaravelData\Data;
        use Spatie\LaravelData\Attributes\DataCollectionOf;
        use Spatie\LaravelData\Attributes\WithCast;
        final class Sandbox extends Data { public function __construct(public readonly string $label) {} }
        final class Mode extends Data { public function __construct(public readonly string $id) {} }
        abstract class Panel extends Data {}
        final class ConsolePanel extends Panel { public function __construct(public readonly int $n) {} }
        PHP;

    private function code(string $body): string
    {
        return self::HEADER . "\n" . $body;
    }

    public function test_flags_a_nested_data_property_wrapper(): void
    {
        $this->assertSame(1, $this->hits($this->code(<<<'PHP'
            final class Payload extends Data {
                public function __construct(public readonly Sandbox $sandbox) {}
            }
            final class Builder {
                public function make(): Payload {
                    return Payload::from(['sandbox' => Sandbox::from(['label' => 'x'])]);
                }
            }
            PHP)));
    }

    public function test_flags_each_wrapper_in_a_collection_literal(): void
    {
        $this->assertSame(2, $this->hits($this->code(<<<'PHP'
            final class Payload extends Data {
                public function __construct(
                    #[DataCollectionOf(Mode::class)]
                    public readonly array $modes,
                ) {}
            }
            final class Builder {
                public function make(): Payload {
                    return Payload::from(['modes' => [Mode::from(['id' => 'a']), Mode::from(['id' => 'b'])]]);
                }
            }
            PHP)));
    }

    public function test_flags_through_a_one_hop_local(): void
    {
        $this->assertSame(1, $this->hits($this->code(<<<'PHP'
            final class Payload extends Data {
                public function __construct(public readonly Sandbox $sandbox) {}
            }
            final class Builder {
                public function make(): Payload {
                    $data = ['sandbox' => Sandbox::from(['label' => 'x'])];
                    return Payload::from($data);
                }
            }
            PHP)));
    }

    public function test_does_not_flag_an_object_source(): void
    {
        $this->assertSame(0, $this->hits($this->code(<<<'PHP'
            final class Payload extends Data {
                public function __construct(public readonly Sandbox $sandbox) {}
            }
            final class Builder {
                public function make(object $model): Payload {
                    return Payload::from(['sandbox' => Sandbox::from($model)]);
                }
            }
            PHP)));
    }

    public function test_does_not_flag_a_subtype_into_a_supertype_slot(): void
    {
        // The slot is the abstract base `Panel`; the explicit `ConsolePanel::from` selects the concrete type.
        $this->assertSame(0, $this->hits($this->code(<<<'PHP'
            final class Payload extends Data {
                public function __construct(public readonly Panel $panel) {}
            }
            final class Builder {
                public function make(): Payload {
                    return Payload::from(['panel' => ConsolePanel::from(['n' => 1])]);
                }
            }
            PHP)));
    }

    public function test_does_not_flag_a_with_cast_slot(): void
    {
        $this->assertSame(0, $this->hits($this->code(<<<'PHP'
            final class Payload extends Data {
                public function __construct(
                    #[WithCast(SomeCast::class)]
                    public Sandbox $sandbox,
                ) {}
            }
            final class Builder {
                public function make(): Payload {
                    return Payload::from(['sandbox' => Sandbox::from(['label' => 'x'])]);
                }
            }
            PHP)));
    }

    public function test_does_not_flag_a_plain_array_slot(): void
    {
        // No #[DataCollectionOf] — a bare array does NOT auto-hydrate, so the ::from is load-bearing.
        $this->assertSame(0, $this->hits($this->code(<<<'PHP'
            final class Payload extends Data {
                public function __construct(public readonly array $sandbox) {}
            }
            final class Builder {
                public function make(): Payload {
                    return Payload::from(['sandbox' => Sandbox::from(['label' => 'x'])]);
                }
            }
            PHP)));
    }

    public function test_does_not_flag_a_per_item_array_map(): void
    {
        // A per-item map is ManualHydrationLoop's turf (fix = ::collect), not this detector's.
        $this->assertSame(0, $this->hits($this->code(<<<'PHP'
            final class Payload extends Data {
                public function __construct(
                    #[DataCollectionOf(Mode::class)]
                    public readonly array $modes,
                ) {}
            }
            final class Builder {
                public function make(array $ids): Payload {
                    return Payload::from(['modes' => array_map(fn ($id) => Mode::from(['id' => $id]), $ids)]);
                }
            }
            PHP)));
    }

    public function test_does_not_flag_a_chained_result(): void
    {
        $this->assertSame(0, $this->hits($this->code(<<<'PHP'
            final class Sized extends Data {
                public function __construct(public readonly string $label) {}
                public function withLabel(string $l): static { return static::from(['label' => $l]); }
            }
            final class Payload extends Data {
                public function __construct(public readonly Sized $sized) {}
            }
            final class Builder {
                public function make(): Payload {
                    return Payload::from(['sized' => Sized::from(['label' => 'x'])->withLabel('y')]);
                }
            }
            PHP)));
    }
}
