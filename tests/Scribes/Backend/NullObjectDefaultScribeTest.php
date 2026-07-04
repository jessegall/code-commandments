<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Scribes\Backend;

use JesseGall\CodeCommandments\Backend\Detector;
use JesseGall\CodeCommandments\Detectors\Backend\Spatie\AllNullableDataDetector;
use JesseGall\CodeCommandments\Scribes\Backend\NullObjectDefaultScribe;
use JesseGall\CodeCommandments\Scribes\RepentScribe;

/**
 * {@see NullObjectDefaultScribe} reshapes an all-nullable Data bag into non-nullable fields
 * defaulting to each type's Null Object — but ONLY where the identity is expressible (a
 * default-constructible value type), and all-or-nothing per class.
 */
final class NullObjectDefaultScribeTest extends ScribeTestCase
{
    protected function detector(): Detector
    {
        return new AllNullableDataDetector();
    }

    protected function scribe(): RepentScribe
    {
        return new NullObjectDefaultScribe();
    }

    private const STUB = <<<'PHP'
        namespace Spatie\LaravelData { class Data {} }

        PHP;

    public function test_reshapes_a_bag_of_default_constructible_value_objects(): void
    {
        $php = "<?php\n\n" . self::STUB . <<<'PHP'
        namespace App {
            use Spatie\LaravelData\Data;

            final class RetryPolicy extends Data {
                public function __construct(public readonly int $times = 3) {}
            }

            final class CachePolicy extends Data {
                public function __construct(public readonly int $ttl = 60) {}
            }

            final class Settings extends Data {
                public function __construct(
                    public readonly ?RetryPolicy $retry = null,
                    public readonly CachePolicy|null $cache = null,
                ) {}
            }
        }
        PHP;

        $fixed = $this->fixStable($php);

        $this->assertStringContainsString('public readonly RetryPolicy $retry = new RetryPolicy(),', $fixed);
        $this->assertStringContainsString('public readonly CachePolicy $cache = new CachePolicy(),', $fixed);
        $this->assertStringNotContainsString('?RetryPolicy', $fixed);
        $this->assertStringNotContainsString('= null', $fixed);
    }

    public function test_inlines_a_null_object_factory_body(): void
    {
        // Callback can't be `new Callback()` (required $event), but it declares its Null Object as a
        // factory `new self(self::NOOP)`. That call can't BE a default, but its body can — inline it.
        $php = "<?php\n\n" . self::STUB . <<<'PHP'
        namespace App {
            use Spatie\LaravelData\Data;

            final class Callback extends Data {
                public const string NOOP = 'noop';
                public function __construct(public readonly string $event) {}
                public static function noOp(): self { return new self(self::NOOP); }
            }

            final class AcceptCallbacks extends Data {
                public function __construct(
                    public readonly ?Callback $startDrag = null,
                    public readonly Callback|null $received = null,
                ) {}
            }
        }
        PHP;

        $fixed = $this->fixStable($php);

        $this->assertStringContainsString('public readonly Callback $startDrag = new Callback(Callback::NOOP),', $fixed);
        $this->assertStringContainsString('public readonly Callback $received = new Callback(Callback::NOOP),', $fixed);
    }

    public function test_inlines_a_factory_body_for_any_value_type_not_just_callbacks(): void
    {
        // Nothing about the reshape is callback-specific: a Duration bag inlines `new self(0)` the
        // same way a callback bag inlines `new self(self::NOOP)`.
        $php = "<?php\n\n" . self::STUB . <<<'PHP'
        namespace App {
            use Spatie\LaravelData\Data;

            final class Duration extends Data {
                public function __construct(public readonly int $ms) {}
                public static function none(): self { return new self(0); }
            }

            final class Timeouts extends Data {
                public function __construct(
                    public readonly ?Duration $connect = null,
                    public readonly ?Duration $read = null,
                ) {}
            }
        }
        PHP;

        $fixed = $this->fixStable($php);

        $this->assertStringContainsString('public readonly Duration $connect = new Duration(0),', $fixed);
        $this->assertStringContainsString('public readonly Duration $read = new Duration(0),', $fixed);
    }

    public function test_does_not_inline_an_ambiguous_pair_of_self_factories(): void
    {
        // Two `new self(...)` factories — which is the identity? Can't tell, so leave it alone.
        $php = "<?php\n\n" . self::STUB . <<<'PHP'
        namespace App {
            use Spatie\LaravelData\Data;

            final class Money extends Data {
                public function __construct(public readonly int $cents) {}
                public static function zero(): self { return new self(0); }
                public static function max(): self { return new self(999); }
            }

            final class Prices extends Data {
                public function __construct(
                    public readonly ?Money $base = null,
                    public readonly ?Money $tax = null,
                ) {}
            }
        }
        PHP;

        $this->assertNotSame([], $this->findings($php));
        $this->assertSame($php, $this->fix($php));
    }

    public function test_leaves_the_class_alone_when_a_value_type_needs_a_constructor_argument(): void
    {
        // Callback has a REQUIRED $event — no `new Callback()`, and the inert value can't be
        // invented — so the whole class stays untouched (the skill's hint guides the human).
        $php = "<?php\n\n" . self::STUB . <<<'PHP'
        namespace App {
            use Spatie\LaravelData\Data;

            final class Callback extends Data {
                public function __construct(public readonly string $event) {}
            }

            final class Hooks extends Data {
                public function __construct(
                    public readonly ?Callback $before = null,
                    public readonly ?Callback $after = null,
                ) {}
            }
        }
        PHP;

        $this->assertNotSame([], $this->findings($php), 'the sin should still fire');
        $this->assertSame($php, $this->fix($php));
    }

    public function test_all_or_nothing_a_single_unresolvable_field_blocks_the_class(): void
    {
        // RetryPolicy resolves; Callback does not → the mixed bag is left entirely alone.
        $php = "<?php\n\n" . self::STUB . <<<'PHP'
        namespace App {
            use Spatie\LaravelData\Data;

            final class RetryPolicy extends Data {
                public function __construct(public readonly int $times = 3) {}
            }

            final class Callback extends Data {
                public function __construct(public readonly string $event) {}
            }

            final class Mixed_ extends Data {
                public function __construct(
                    public readonly ?RetryPolicy $ok = null,
                    public readonly ?Callback $bad = null,
                ) {}
            }
        }
        PHP;

        $this->assertSame($php, $this->fix($php));
    }

    public function test_does_not_reshape_nullable_scalars(): void
    {
        // A bag of nullable scalars has no Null Object to name — left alone.
        $php = "<?php\n\n" . self::STUB . <<<'PHP'
        namespace App {
            use Spatie\LaravelData\Data;

            final class Filters extends Data {
                public function __construct(
                    public readonly ?int $min = null,
                    public readonly ?string $tag = null,
                ) {}
            }
        }
        PHP;

        $this->assertNotSame([], $this->findings($php));
        $this->assertSame($php, $this->fix($php));
    }
}
