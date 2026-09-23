<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\Python;

use JesseGall\CodeCommandments\Detectors\Python\InventedDefaultDetector;
use JesseGall\CodeCommandments\Python\Detector;
use PHPUnit\Framework\TestCase;

final class InventedDefaultDetectorTest extends TestCase
{
    use ProvesAPythonRule;
    use FlagsEachSnippetOnce;

    private function rule(): Detector
    {
        return new InventedDefaultDetector();
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function thisSin(): iterable
    {
        yield 'an empty string, positional' => ["send(order.email or '')\n"];
        yield 'a zero, keyword' => ["charge(amount=order.total or 0)\n"];
        yield 'False' => ["flag(order.paid or False)\n"];
        yield 'inside a conversion' => ["label = str(row.get('name') or '')\n"];
        yield 'written longhand' => ["send(order.email if order.email else '')\n"];
        yield 'longhand against None' => ["charge(amount=order.total if order.total is not None else 0)\n"];
        yield 'returned by a lookup helper on a miss' => ["def text_of(raw, key):\n    value = raw.get(key)\n    if value is None:\n        return ''\n    return str(value)\n"];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function notThisSin(): iterable
    {
        yield 'a real default' => ["send(order.currency or 'EUR')\n"];
        yield 'a keyed read naming the value an absent key has' => ["ship(row.get('carrier', ''))\n"];
        yield 'a keyed read with a real default' => ["send(row.get('currency', 'EUR'))\n"];
        yield 'an empty collection' => ["render(order.lines or [])\n"];
        yield 'None passed on' => ["send(order.email or None)\n"];
        yield 'not an argument' => ["email = order.email or ''\n"];
        yield 'the old conditional' => ["mode(reading and 'r' or '')\n"];
        yield 'the callee itself' => ["(handler or '')()\n"];
        yield 'a longhand conditional on something else' => ["send(order.email if order.verified else '')\n"];
        yield 'an empty return from a function that reads no parameter by key' => ["def title(order):\n    if order.draft:\n        return ''\n    return order.title\n"];
    }
}
