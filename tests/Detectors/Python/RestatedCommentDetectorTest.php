<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\Python;

use JesseGall\CodeCommandments\Detectors\Python\RestatedCommentDetector;
use JesseGall\CodeCommandments\Python\Detector;
use PHPUnit\Framework\TestCase;

final class RestatedCommentDetectorTest extends TestCase
{
    use ProvesAPythonRule;
    use FlagsEachSnippetOnce;

    private function rule(): Detector
    {
        return new RestatedCommentDetector();
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function thisSin(): iterable
    {
        yield 'narrating an assignment' => ["def price(order):\n    # set the total to the order total\n    total = order.total\n    return total\n"];
        yield 'narrating a loop' => ["def ship(orders):\n    # loop over each order\n    for order in orders:\n        order.ship()\n"];
        yield 'narrating a return' => ["class Cart:\n    def size(self):\n        # return the lines count\n        return self.lines.count()\n"];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function notThisSin(): iterable
    {
        yield 'a reason the code cannot state' => ["def price(order):\n    # the warehouse rounds down, so this must too\n    total = order.total\n    return total\n"];
        yield 'a one-word label' => ["def flush(buffer):\n    # flush\n    buffer.flush()\n"];
        yield 'commented-out code' => ["def price(order):\n    # total = order.total * 2\n    total = order.total\n    return total\n"];
        yield 'a module-level comment' => ["# set the rate to three\nrate = 3\n"];
    }
}
