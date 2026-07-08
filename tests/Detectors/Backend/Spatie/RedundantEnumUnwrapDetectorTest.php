<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\Backend\Spatie;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Detectors\Backend\Spatie\RedundantEnumUnwrapDetector;
use JesseGall\CodeCommandments\Scribes\Backend\RedundantEnumUnwrapScribe;
use PHPUnit\Framework\TestCase;

final class RedundantEnumUnwrapDetectorTest extends TestCase
{
    private const string PRELUDE = <<<'PHP'
        <?php
        namespace App;
        use Spatie\LaravelData\Data;
        enum Status: string { case Draft = 'draft'; case Live = 'live'; }
        enum Priority: int { case Low = 1; case High = 2; }
        class Order { public function __construct(public Status $status, public Priority $priority) {} }
        PHP;

    public function test_flags_an_enum_value_fed_into_its_own_enum_slot(): void
    {
        $this->assertSame(1, $this->hits(<<<'PHP'
        class OrderData extends Data { public function __construct(public Status $status) {} }
        class Factory {
            public function make(Order $order): OrderData {
                return OrderData::from(['status' => $order->status->value]);
            }
        }
        PHP));
    }

    public function test_flags_a_backed_int_enum_too(): void
    {
        $this->assertSame(1, $this->hits(<<<'PHP'
        class TicketData extends Data { public function __construct(public Priority $priority) {} }
        class Tickets {
            public function build(Order $order): TicketData {
                return TicketData::from(['priority' => $order->priority->value]);
            }
        }
        PHP));
    }

    public function test_does_not_flag_when_the_slot_is_a_scalar(): void
    {
        // The destination is a plain string — you MUST pass the scalar; unwrapping is correct.
        $this->assertSame(0, $this->hits(<<<'PHP'
        class Row extends Data { public function __construct(public string $status) {} }
        class Rows {
            public function build(Order $order): Row {
                return Row::from(['status' => $order->status->value]);
            }
        }
        PHP));
    }

    public function test_does_not_flag_the_enum_passed_directly(): void
    {
        // The righteous form — the enum itself is passed, nothing to unwrap.
        $this->assertSame(0, $this->hits(<<<'PHP'
        class OrderData extends Data { public function __construct(public Status $status) {} }
        class Factory {
            public function make(Order $order): OrderData {
                return OrderData::from(['status' => $order->status]);
            }
        }
        PHP));
    }

    public function test_traces_the_unwrap_one_factory_hop_to_the_from_call(): void
    {
        // The `->value` is built a factory-method away from the `::from` — `make()` forwards the array to
        // `build($attrs)` which does `OrderData::from($attrs)`. The origin is still caught.
        $this->assertSame(1, $this->hits(<<<'PHP'
        class OrderData extends Data { public function __construct(public Status $status) {} }
        class Factory {
            public function make(Order $order): OrderData {
                return $this->build(['status' => $order->status->value]);
            }
            private function build(array $attrs): OrderData {
                return OrderData::from($attrs);
            }
        }
        PHP));
    }

    public function test_does_not_flag_a_value_read_outside_a_from_call(): void
    {
        // `->value` in an ordinary array, not a Data::from — no auto-hydration to lean on.
        $this->assertSame(0, $this->hits(<<<'PHP'
        class Reporter {
            public function row(Order $order): array {
                return ['status' => $order->status->value];
            }
        }
        PHP));
    }

    public function test_does_not_flag_a_value_property_on_a_non_enum(): void
    {
        // `->value` here is an ordinary property of a value object, not an enum backing.
        $this->assertSame(0, $this->hits(<<<'PHP'
        class Money { public function __construct(public int $value) {} }
        class Wallet { public function __construct(public Money $balance) {} }
        class WalletData extends Data { public function __construct(public int $balance) {} }
        class Wallets {
            public function build(Wallet $wallet): WalletData {
                return WalletData::from(['balance' => $wallet->balance->value]);
            }
        }
        PHP));
    }

    public function test_the_scribe_drops_the_value_unwrap(): void
    {
        $php = self::PRELUDE . <<<'PHP'
        class OrderData extends Data { public function __construct(public Status $status) {} }
        class Factory {
            public function make(Order $order): OrderData {
                return OrderData::from(['status' => $order->status->value]);
            }
        }
        PHP;

        $codebase = Codebase::fromString($php, '/proj/app/Factory.php');
        $rewrites = new RedundantEnumUnwrapScribe()->rewrite((new RedundantEnumUnwrapDetector)->find($codebase));

        $this->assertStringContainsString("'status' => \$order->status]", reset($rewrites));
        $this->assertStringNotContainsString('->status->value', reset($rewrites));
    }

    private function hits(string $body): int
    {
        return count((new RedundantEnumUnwrapDetector)->find(Codebase::fromString(self::PRELUDE . $body, '/proj/app/File.php')));
    }
}
