<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\Python;

use JesseGall\CodeCommandments\Detectors\Python\RepeatedGuardDetector;
use JesseGall\CodeCommandments\Python\Detector;
use PHPUnit\Framework\TestCase;

final class RepeatedGuardDetectorTest extends TestCase
{
    use ProvesARecurringPythonRule;

    private function rule(): Detector
    {
        return new RepeatedGuardDetector();
    }

    /**
     * @return iterable<string, array{string, list<int>}>
     */
    public static function recurring(): iterable
    {
        yield 'written in two functions' => ["def ship(order):\n    if order.paid and not order.cancelled:\n        send(order)\n\n\ndef invoice(order):\n    return order.paid and not order.cancelled\n", [2, 7]];
        yield 'reordered and read through a local' => ["def ship(order):\n    if order.paid and order.lines:\n        send(order)\n\n\ndef invoice(order):\n    paid = order.paid\n    if order.lines and paid:\n        bill(order)\n", [2, 8]];
        yield 'the outermost and of a longer chain' => ["def a(o):\n    return o.paid and o.lines and o.owner\n\n\ndef b(o):\n    return o.owner and o.paid and o.lines\n", [2, 6]];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function notThisSin(): iterable
    {
        yield 'written once' => ["def ship(order):\n    if order.paid and not order.cancelled:\n        send(order)\n"];
        yield 'trivial conditions with no reach' => ["def a(x, y):\n    return x and y\n\n\ndef b(x, y):\n    return x and y\n"];
        yield 'a pure type check, left to the type-guard rule' => ["def a(node):\n    return isinstance(node, Call) and isinstance(node.func, Attribute)\n\n\ndef b(node):\n    return isinstance(node, Call) and isinstance(node.func, Attribute)\n"];
        yield 'a value reached through and' => ["def a(info):\n    obj = info and info.ref.load()\n    return obj\n\n\ndef b(info):\n    obj = info and info.ref.load()\n    return obj\n"];
        yield 'different conditions' => ["def a(o):\n    return o.paid and o.lines\n\n\ndef b(o):\n    return o.paid and o.owner\n"];
    }
}
