<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\Backend\Spatie;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Detectors\Backend\Spatie\DerivedCollectionShouldCastDetector;
use PHPUnit\Framework\TestCase;

/**
 * `array_map(E::for(...), $xs)` filling a `#[DataCollectionOf(E)]` is a per-item derivation that belongs in
 * a cast. A `::from` map (ManualHydrationLoop's), a service-closed factory, a scalar map, a non-collection
 * slot, and a post-processed element are all spared.
 */
final class DerivedCollectionShouldCastDetectorTest extends TestCase
{
    private function hits(string $php): int
    {
        return count(new DerivedCollectionShouldCastDetector()->find(Codebase::fromString($php)));
    }

    private const HEADER = <<<'PHP'
        <?php
        namespace App;
        use Spatie\LaravelData\Data;
        use Spatie\LaravelData\Attributes\DataCollectionOf;
        enum Status: string { case A = 'a'; case B = 'b'; }
        final class Style extends Data {
            public function __construct(public readonly string $label) {}
            public static function for(Status $s): self { return new self($s->value); }
            public static function from(mixed ...$p): static { return new self(''); }
        }
        PHP;

    private function code(string $body): string
    {
        return self::HEADER . "\n" . $body;
    }

    public function test_flags_a_first_class_callable_derivation(): void
    {
        $this->assertSame(1, $this->hits($this->code(<<<'PHP'
            final class Payload extends Data {
                public function __construct(
                    #[DataCollectionOf(Style::class)]
                    public readonly array $styles,
                ) {}
            }
            final class Builder {
                public function make(): Payload {
                    return Payload::from(['styles' => array_map(Style::for(...), Status::cases())]);
                }
            }
            PHP)));
    }

    public function test_flags_an_arrow_derivation(): void
    {
        $this->assertSame(1, $this->hits($this->code(<<<'PHP'
            final class Payload extends Data {
                public function __construct(
                    #[DataCollectionOf(Style::class)]
                    public readonly array $styles,
                ) {}
            }
            final class Builder {
                public function make(array $cases): Payload {
                    return Payload::from(['styles' => array_map(fn (Status $s) => Style::for($s), $cases)]);
                }
            }
            PHP)));
    }

    public function test_does_not_flag_a_from_map(): void
    {
        // `array_map(E::from(...))` is ManualHydrationLoop's turf (fix = ::collect), not a cast.
        $this->assertSame(0, $this->hits($this->code(<<<'PHP'
            final class Payload extends Data {
                public function __construct(
                    #[DataCollectionOf(Style::class)]
                    public readonly array $styles,
                ) {}
            }
            final class Builder {
                public function make(array $rows): Payload {
                    return Payload::from(['styles' => array_map(Style::from(...), $rows)]);
                }
            }
            PHP)));
    }

    public function test_does_not_flag_a_service_closed_factory(): void
    {
        // The factory reaches `$this->theme` — a per-item cast can't be handed that context.
        $this->assertSame(0, $this->hits($this->code(<<<'PHP'
            final class Payload extends Data {
                public function __construct(
                    #[DataCollectionOf(Style::class)]
                    public readonly array $styles,
                ) {}
            }
            final class Builder {
                private string $theme = 'dark';
                public function make(array $cases): Payload {
                    return Payload::from(['styles' => array_map(fn (Status $s) => Style::for($s, $this->theme), $cases)]);
                }
            }
            PHP)));
    }

    public function test_does_not_flag_a_scalar_map(): void
    {
        $this->assertSame(0, $this->hits($this->code(<<<'PHP'
            final class Payload extends Data {
                public function __construct(public readonly array $labels) {}
            }
            final class Builder {
                public function make(array $styles): Payload {
                    return Payload::from(['labels' => array_map(fn (Style $s) => $s->label, $styles)]);
                }
            }
            PHP)));
    }

    public function test_does_not_flag_a_post_processed_element(): void
    {
        $this->assertSame(0, $this->hits($this->code(<<<'PHP'
            final class Payload extends Data {
                public function __construct(
                    #[DataCollectionOf(Style::class)]
                    public readonly array $styles,
                ) {}
            }
            final class Builder {
                public function make(array $cases): Payload {
                    return Payload::from(['styles' => array_map(fn (Status $s) => Style::for($s)->label, $cases)]);
                }
            }
            PHP)));
    }
}
