<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\Python;

use JesseGall\CodeCommandments\Detectors\Python\CeremonyDocblockDetector;
use JesseGall\CodeCommandments\Python\Detector;
use PHPUnit\Framework\TestCase;

final class CeremonyDocblockDetectorTest extends TestCase
{
    use ProvesAPythonRule;
    use FlagsEachSnippetOnce;

    private function rule(): Detector
    {
        return new CeremonyDocblockDetector();
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function thisSin(): iterable
    {
        yield 'Google entries restating annotations' => ["def price(order: Order, rate: float) -> int:\n    \"\"\"\n    Args:\n        order (Order):\n        rate (float):\n\n    Returns:\n        int\n    \"\"\"\n    return order.total\n"];
        yield 'Sphinx fields restating annotations' => ["def price(order: Order) -> int:\n    \"\"\"\n    :param order:\n    :type order: Order\n    :rtype: int\n    \"\"\"\n    return order.total\n"];
        yield 'NumPy parameters restating annotations' => ["class Cart:\n    def add(self, sku: str, quantity: int) -> None:\n        \"\"\"\n        Parameters\n        ----------\n        sku : str\n        quantity : int\n        \"\"\"\n        self.lines.append((sku, quantity))\n"];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function notThisSin(): iterable
    {
        yield 'a prose summary' => ["def price(order: Order) -> int:\n    \"\"\"The order's total in cents.\n\n    Args:\n        order (Order):\n    \"\"\"\n    return order.total\n"];
        yield 'an entry with a description' => ["def price(order: Order) -> int:\n    \"\"\"\n    Args:\n        order (Order): the order, with its lines priced.\n    \"\"\"\n    return order.total\n"];
        yield 'a type for an unannotated parameter' => ["def price(order) -> int:\n    \"\"\"\n    Args:\n        order (Order):\n    \"\"\"\n    return order.total\n"];
        yield 'a Raises section' => ["def price(order: Order) -> int:\n    \"\"\"\n    Args:\n        order (Order):\n\n    Raises:\n        ValueError: when the order is empty.\n    \"\"\"\n    return order.total\n"];
        yield 'no docstring' => ["def price(order: Order) -> int:\n    return order.total\n"];
    }
}
