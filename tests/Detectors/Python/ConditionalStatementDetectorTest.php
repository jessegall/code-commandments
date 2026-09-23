<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\Python;

use JesseGall\CodeCommandments\Detectors\Python\ConditionalStatementDetector;
use JesseGall\CodeCommandments\Python\Detector;
use PHPUnit\Framework\TestCase;

final class ConditionalStatementDetectorTest extends TestCase
{
    use ProvesAPythonRule;
    use FlagsEachSnippetOnce;

    private function rule(): Detector
    {
        return new ConditionalStatementDetector();
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function thisSin(): iterable
    {
        yield 'two calls' => ["def a(order):\n    order.ship() if order.paid else order.hold()\n"];
        yield 'a call against nothing' => ["def a(log, verbose):\n    log.debug('x') if verbose else None\n"];
        yield 'at module level' => ["start() if READY else wait()\n"];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function notThisSin(): iterable
    {
        yield 'assigned' => ["def a(order):\n    state = 'paid' if order.paid else 'open'\n"];
        yield 'returned' => ["def a(order):\n    return order.ship() if order.paid else None\n"];
        yield 'handed to a call' => ["def a(order):\n    log('paid' if order.paid else 'open')\n"];
        yield 'an if' => ["def a(order):\n    if order.paid:\n        order.ship()\n"];
    }
}
