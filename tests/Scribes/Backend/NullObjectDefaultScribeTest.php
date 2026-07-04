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
