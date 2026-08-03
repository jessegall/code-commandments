<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\Backend;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Detectors\Backend\MutableStaticStateDetector;
use PHPUnit\Framework\TestCase;

final class MutableStaticStateDetectorTest extends TestCase
{
    public function test_flags_writing_a_static_property_after_declaration(): void
    {
        $code = <<<'PHP'
        <?php
        class Rates {
            private static array $table = [];

            public static function load(array $rates): void {
                self::$table = $rates;
            }
        }
        class Counter {
            private static int $seen = 0;

            public static function tick(): void {
                static::$seen++;
            }
        }
        class Booking {
            public static function reset(): void {
                Rates::$table = [];
            }
        }
        PHP;

        $hits = (new MutableStaticStateDetector)->find(Codebase::fromString($code));

        $scopes = array_map(static fn ($m): string => $m->scope(), $hits);
        sort($scopes);

        $this->assertSame(['Booking::reset', 'Counter::tick', 'Rates::load'], $scopes);
    }

    public function test_leaves_memoisation_constants_and_instance_state_alone(): void
    {
        $code = <<<'PHP'
        <?php
        class Parsed {
            private static array $cache = [];

            public static function of(string $key): string {
                self::$cache[$key] ??= strtoupper($key);

                return self::$cache[$key];
            }
        }
        class Limits {
            private const int MAX = 10;

            private static array $defaults = ['a' => 1];

            public static function max(): int {
                return self::MAX;
            }

            public static function defaults(): array {
                return self::$defaults;
            }
        }
        class Session {
            private int $hits = 0;

            public function touch(): void {
                $this->hits++;
            }
        }
        PHP;

        $hits = (new MutableStaticStateDetector)->find(Codebase::fromString($code));

        // `??=` fills a memo; a declaration initialiser is not a write; instance state is not global.
        $this->assertSame([], array_map(static fn ($m): string => $m->scope(), $hits));
    }
}
