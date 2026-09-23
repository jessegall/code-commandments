<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\Python;

use JesseGall\CodeCommandments\Detectors\Python\ConvertedArgumentDetector;
use JesseGall\CodeCommandments\Py\Codebase;
use JesseGall\CodeCommandments\Py\ExprMatch;
use JesseGall\CodeCommandments\Py\TypeBridge;
use JesseGall\CodeCommandments\Support\Directory;
use PHPUnit\Framework\TestCase;

final class ConvertedArgumentDetectorTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        if (TypeBridge::located()->isNone()) {
            $this->markTestSkipped('there is no python3, so there is no mypy bridge to type Python with');
        }

        $this->root = sys_get_temp_dir() . '/converted-arg-' . bin2hex(random_bytes(4));
        mkdir("{$this->root}/shop", 0777, true);
        touch("{$this->root}/shop/__init__.py");
        file_put_contents("{$this->root}/shop/order.py", "class Order:\n    def __init__(self, status: str, total: int) -> None:\n        self.status = status\n        self.total = total\n");
    }

    protected function tearDown(): void
    {
        if (isset($this->root)) {
            Directory::delete($this->root);
        }
    }

    public function test_flags_calls_that_all_convert_what_they_hand_a_scalar_parameter(): void
    {
        $this->write('receipts.py', <<<'PY'
            from shop.order import Order

            def receipt_for(order_id: str) -> str:
                return f"receipt {order_id}"

            def email(order: Order) -> str:
                return receipt_for(str(order.total))

            def reprint(order: Order) -> str:
                return receipt_for(order_id=str(order.total))
            PY);

        $this->assertSame([7, 10], $this->lines());
    }

    public function test_leaves_a_function_call_an_object_parameter_and_a_rare_conversion(): void
    {
        $this->write('receipts.py', <<<'PY'
            from decimal import Decimal
            from shop.order import Order

            def receipt_for(order_id: str) -> str:
                return order_id

            def priced(amount: Decimal) -> Decimal:
                return amount

            def counted(size: int) -> int:
                return size

            def a(order: Order, raw: str) -> None:
                receipt_for(str(order.total))
                receipt_for(raw)
                receipt_for(raw)
                priced(Decimal(raw))
                priced(Decimal(raw))
                counted(len(raw))
                counted(len(raw))

            def b(raw: str) -> None:
                receipt_for(raw)
            PY);

        $this->assertSame([], $this->lines());
    }

    private function write(string $file, string $source): void
    {
        file_put_contents("{$this->root}/shop/{$file}", $source . "\n");
    }

    /**
     * @return list<int>
     */
    private function lines(): array
    {
        return array_map(static fn (ExprMatch $match): int => $match->line(), new ConvertedArgumentDetector()->find(Codebase::scan($this->root)));
    }
}
