<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\Python;

use JesseGall\CodeCommandments\Detectors\Python\FeatureEnvyDetector;
use JesseGall\CodeCommandments\Py\Codebase;
use JesseGall\CodeCommandments\Py\NodeMatch;
use PHPUnit\Framework\TestCase;

final class FeatureEnvyDetectorTest extends TestCase
{
    private const string MODEL = <<<'PY'
        from abc import ABC, abstractmethod


        class Line:
            price: int = 0
            sku: str = ""


        class Order:
            lines: list[Line]
            status: str = "open"
            total: int = 0


        class Printer:
            def print(self, line: Line) -> None:
                pass

        PY;

    public function test_flags_a_method_that_loops_or_writes_another_objects_state(): void
    {
        $this->assertSame(['heaviest', 'close'], $this->flagged(<<<'PY'
            class Reports:
                def heaviest(self, order: Order) -> int:
                    best = 0
                    for line in order.lines:
                        best = max(best, line.price)
                    return best


            class Closer:
                def close(self, order: Order) -> None:
                    order.status = "closed"
                    order.total = sum(line.price for line in order.lines)
            PY));
    }

    public function test_leaves_orchestration_a_contract_a_factory_and_its_own_state(): void
    {
        $this->assertSame([], $this->flagged(<<<'PY'
            class Pricing(ABC):
                @abstractmethod
                def price(self, order: Order) -> int: ...


            class Flat(Pricing):
                def price(self, order: Order) -> int:
                    total = 0
                    for line in order.lines:
                        total += line.price
                    return total


            class Receipts:
                def __init__(self, printer: Printer) -> None:
                    self.printer = printer

                def print_all(self, order: Order) -> None:
                    for line in order.lines:
                        self.printer.print(line)


            class Copier:
                def copy(self, order: Order) -> Order:
                    fresh = Order()
                    for line in order.lines:
                        fresh.lines.append(line)
                    return fresh


            class Tally:
                def __init__(self) -> None:
                    self.count = 0
                    self.seen = 0
                    self.last = 0

                def add(self, order: Order) -> None:
                    for line in order.lines:
                        self.count += 1
                        self.seen += line.price
                        self.last = line.price
            PY));
    }

    /**
     * @return list<string>
     */
    private function flagged(string $classes): array
    {
        return array_map(static fn (NodeMatch $match): string => $match->name(), new FeatureEnvyDetector()->find(Codebase::fromString(self::MODEL . "\n\n" . $classes)));
    }
}
