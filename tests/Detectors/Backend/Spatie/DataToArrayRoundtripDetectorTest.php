<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\Backend\Spatie;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Detectors\Backend\Spatie\DataToArrayRoundtripDetector;
use PHPUnit\Framework\TestCase;

/**
 * `X::from(...)->toArray()` sitting in a `::from` slot typed `X` re-hydrates it — build → array → build.
 * A `->toArray()` feeding a genuine array sink (a plain-array slot, or no slot) is spared.
 */
final class DataToArrayRoundtripDetectorTest extends TestCase
{
    private function hits(string $php): int
    {
        return count(new DataToArrayRoundtripDetector()->find(Codebase::fromString($php)));
    }

    private const HEADER = <<<'PHP'
        <?php
        namespace App;
        use Spatie\LaravelData\Data;
        use Spatie\LaravelData\Attributes\DataCollectionOf;
        final class Inner extends Data { public function __construct(public readonly string $label) {} }
        PHP;

    private function code(string $body): string
    {
        return self::HEADER . "\n" . $body;
    }

    public function test_flags_a_roundtrip_into_a_nested_data_slot(): void
    {
        $this->assertSame(1, $this->hits($this->code(<<<'PHP'
            final class Outer extends Data {
                public function __construct(public readonly Inner $inner) {}
            }
            final class Builder {
                public function make(Inner $inner): Outer {
                    return Outer::from(['inner' => $inner->toArray()]);
                }
            }
            PHP)));
    }

    public function test_flags_a_roundtrip_via_from(): void
    {
        $this->assertSame(1, $this->hits($this->code(<<<'PHP'
            final class Outer extends Data {
                public function __construct(public readonly Inner $inner) {}
            }
            final class Builder {
                public function make(array $raw): Outer {
                    return Outer::from(['inner' => Inner::from($raw)->toArray()]);
                }
            }
            PHP)));
    }

    public function test_does_not_flag_a_plain_array_slot(): void
    {
        // The slot is a bare array — it does NOT re-hydrate, so ->toArray() is the actual value wanted.
        $this->assertSame(0, $this->hits($this->code(<<<'PHP'
            final class Outer extends Data {
                public function __construct(public readonly array $inner) {}
            }
            final class Builder {
                public function make(Inner $inner): Outer {
                    return Outer::from(['inner' => $inner->toArray()]);
                }
            }
            PHP)));
    }

    public function test_does_not_flag_toarray_outside_a_hydration_slot(): void
    {
        $this->assertSame(0, $this->hits($this->code(<<<'PHP'
            final class Builder {
                public function dump(Inner $inner): array {
                    return $inner->toArray();
                }
            }
            PHP)));
    }

    public function test_does_not_flag_a_non_data_receiver(): void
    {
        $this->assertSame(0, $this->hits($this->code(<<<'PHP'
            final class Bag { public function toArray(): array { return []; } }
            final class Outer extends Data {
                public function __construct(public readonly Inner $inner) {}
            }
            final class Builder {
                public function make(Bag $bag): Outer {
                    return Outer::from(['inner' => $bag->toArray()]);
                }
            }
            PHP)));
    }
}
