<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\Backend\Spatie;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Detectors\Backend\Spatie\RedundantNativeCastDetector;
use PHPUnit\Framework\TestCase;

/**
 * `Enum::from($x)` / `new DateTime($x)` / `Carbon::parse($x)` in a `::from` slot typed as that enum or a
 * date is redundant — Spatie auto-casts the raw scalar. `tryFrom`, a timezone/format arg, a chained result,
 * and a `#[WithCast]` slot are spared.
 */
final class RedundantNativeCastDetectorTest extends TestCase
{
    private function hits(string $php): int
    {
        return count(new RedundantNativeCastDetector()->find(Codebase::fromString($php)));
    }

    private const HEADER = <<<'PHP'
        <?php
        namespace App;
        use Spatie\LaravelData\Data;
        use Spatie\LaravelData\Attributes\WithCast;
        enum Status: string { case A = 'a'; case B = 'b'; }
        final class Carbon { public static function parse(string $x): self { return new self(); } }
        PHP;

    private function code(string $body): string
    {
        return self::HEADER . "\n" . $body;
    }

    public function test_flags_an_enum_from_at_a_hydration_site(): void
    {
        $this->assertSame(1, $this->hits($this->code(<<<'PHP'
            final class Payload extends Data {
                public function __construct(public readonly Status $status) {}
            }
            final class Builder {
                public function make(array $raw): Payload {
                    return Payload::from(['status' => Status::from($raw['status'])]);
                }
            }
            PHP)));
    }

    public function test_flags_a_new_datetime(): void
    {
        $this->assertSame(1, $this->hits($this->code(<<<'PHP'
            final class Payload extends Data {
                public function __construct(public readonly \DateTimeInterface $at) {}
            }
            final class Builder {
                public function make(array $raw): Payload {
                    return Payload::from(['at' => new \DateTime($raw['at'])]);
                }
            }
            PHP)));
    }

    public function test_flags_a_carbon_parse(): void
    {
        $this->assertSame(1, $this->hits($this->code(<<<'PHP'
            final class Payload extends Data {
                public function __construct(public readonly Carbon $at) {}
            }
            final class Builder {
                public function make(string $raw): Payload {
                    return Payload::from(['at' => Carbon::parse($raw)]);
                }
            }
            PHP)));
    }

    public function test_does_not_flag_try_from(): void
    {
        $this->assertSame(0, $this->hits($this->code(<<<'PHP'
            final class Payload extends Data {
                public function __construct(public readonly ?Status $status) {}
            }
            final class Builder {
                public function make(array $raw): Payload {
                    return Payload::from(['status' => Status::tryFrom($raw['status'])]);
                }
            }
            PHP)));
    }

    public function test_does_not_flag_a_timezone_argument(): void
    {
        $this->assertSame(0, $this->hits($this->code(<<<'PHP'
            final class Payload extends Data {
                public function __construct(public readonly \DateTimeInterface $at) {}
            }
            final class Builder {
                public function make(string $raw, \DateTimeZone $tz): Payload {
                    return Payload::from(['at' => new \DateTime($raw, $tz)]);
                }
            }
            PHP)));
    }

    public function test_does_not_flag_a_chained_result(): void
    {
        $this->assertSame(0, $this->hits($this->code(<<<'PHP'
            final class Payload extends Data {
                public function __construct(public readonly Carbon $at) {}
            }
            final class Builder {
                public function make(string $raw): Payload {
                    return Payload::from(['at' => Carbon::parse($raw)->startOfDay()]);
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
                    public Status $status,
                ) {}
            }
            final class Builder {
                public function make(array $raw): Payload {
                    return Payload::from(['status' => Status::from($raw['status'])]);
                }
            }
            PHP)));
    }
}
