<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\Python;

use JesseGall\CodeCommandments\Detectors\Python\ComputedBooleanArgumentDetector;
use JesseGall\CodeCommandments\Py\Codebase;
use JesseGall\CodeCommandments\Py\NodeMatch;
use JesseGall\CodeCommandments\Py\TypeBridge;
use JesseGall\CodeCommandments\Support\Directory;
use PHPUnit\Framework\TestCase;

final class ComputedBooleanArgumentDetectorTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        if (TypeBridge::located()->isNone()) {
            $this->markTestSkipped('there is no python3, so there is no mypy bridge to type Python with');
        }

        $this->root = sys_get_temp_dir() . '/computed-bool-' . bin2hex(random_bytes(4));
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

    public function test_flags_a_method_every_caller_feeds_flags_asked_of_one_object(): void
    {
        $this->write('label.py', <<<'PY'
            from shop.order import Order


            class Label:
                def text(self, paid: bool, large: bool) -> str:
                    if paid and not large:
                        return "small paid"
                    return "other"


            def print_label(order: Order, label: Label) -> str:
                return label.text(order.status == "paid", order.total > 100)


            def email_label(order: Order, label: Label) -> str:
                return label.text(paid=order.status == "paid", large=order.total > 100)
            PY);

        $this->assertSame(['FunctionDef text'], $this->scopes());
    }

    public function test_flags_a_method_that_matches_on_its_flags_called_through_an_attribute(): void
    {
        $this->write('label.py', <<<'PY'
            from shop.order import Order

            class Label:
                def text(self, paid: bool, large: bool) -> str:
                    match (paid, large):
                        case (True, _):
                            return "paid"
                        case _:
                            return "other"

            class Printer:
                def __init__(self, label: Label) -> None:
                    self.label = label

                def run(self, order: Order) -> str:
                    return self.label.text(order.status == "paid", order.total > 100)

            def email_label(order: Order, label: Label) -> str:
                return label.text(order.status == "paid", order.total > 100)
            PY);

        $this->assertSame(['FunctionDef text'], $this->scopes());
    }

    public function test_leaves_flags_asked_of_different_objects_or_a_single_caller(): void
    {
        $this->write('label.py', <<<'PY'
            from shop.order import Order


            class Parcel:
                def __init__(self, weight: int) -> None:
                    self.weight = weight


            class Label:
                def text(self, paid: bool, heavy: bool) -> str:
                    return "x" if paid or heavy else "y"


            def print_label(order: Order, parcel: Parcel, label: Label) -> str:
                return label.text(order.status == "paid", parcel.weight > 5)


            def email_label(order: Order, label: Label) -> str:
                return label.text(order.status == "paid", order.total > 100)
            PY);

        $this->assertSame([], $this->scopes());
    }

    public function test_leaves_a_flag_that_is_stored_rather_than_decided_on(): void
    {
        $this->write('label.py', <<<'PY'
            from shop.order import Order


            class Label:
                def remember(self, paid: bool) -> None:
                    self.paid = paid


            def a(order: Order, label: Label) -> None:
                label.remember(order.status == "paid")


            def b(order: Order, label: Label) -> None:
                label.remember(order.status == "paid")
            PY);

        $this->assertSame([], $this->scopes());
    }

    private function write(string $file, string $source): void
    {
        file_put_contents("{$this->root}/shop/{$file}", $source . "\n");
    }

    /**
     * @return list<string>
     */
    private function scopes(): array
    {
        return array_map(static fn (NodeMatch $match): string => $match->scope(), new ComputedBooleanArgumentDetector()->find(Codebase::scan($this->root)));
    }
}
