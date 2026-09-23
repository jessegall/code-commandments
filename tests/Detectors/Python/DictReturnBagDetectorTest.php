<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\Python;

use JesseGall\CodeCommandments\Detectors\Python\DictReturnBagDetector;
use JesseGall\CodeCommandments\Python\Detector;
use PHPUnit\Framework\TestCase;

final class DictReturnBagDetectorTest extends TestCase
{
    use ProvesAPythonRule;
    use FlagsEachSnippetOnce;

    private function rule(): Detector
    {
        return new DictReturnBagDetector();
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function thisSin(): iterable
    {
        yield 'a record built from several sources' => ["def quote(order, rates):\n    return {'total': order.total(), 'tax': rates.vat(order), 'currency': 'EUR'}\n"];
        yield 'a method assembling a result' => ["class Checkout:\n    def summary(self, basket, discount):\n        return {'lines': len(basket), 'discount': discount.amount}\n"];
        yield 'a module function with locals' => ["def parse(raw):\n    name, _, rest = raw.partition(':')\n    return {'name': name, 'rest': rest}\n"];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function notThisSin(): iterable
    {
        yield 'one key' => ["def a(x):\n    return {'id': x.id}\n"];
        yield 'a spread of another dict' => ["def a(base, x):\n    return {**base, 'id': x.id, 'name': x.name}\n"];
        yield 'a nested payload' => ["def a(x, y):\n    return {'data': {'id': x.id}, 'meta': y.page}\n"];
        yield 'a json schema' => ["def schema():\n    return {'type': 'object', 'properties': {}}\n"];
        yield 'a projection of self' => ["class Order:\n    def to_dict(self):\n        return {'id': self.id, 'total': self.total}\n"];
        yield 'a projection of one parameter' => ["def row(order):\n    return {'id': order.id, 'total': order.total}\n"];
        yield 'a table of members' => ["def colours():\n    return {'ok': Colour.GREEN, 'bad': Colour.RED}\n"];
        yield 'a dunder protocol' => ["class Job:\n    def __getstate__(self):\n        return {'id': self.name, 'at': when()}\n"];
        yield 'a TypedDict return' => ["class Quote(TypedDict):\n    total: int\n    tax: int\n\ndef quote(order, rates) -> Quote:\n    return {'total': order.total(), 'tax': rates.vat(order)}\n"];
        yield 'a projection through calls' => ["class Usage:\n    def to_json(self):\n        return {'used': round(max(0, self.used), 1), 'label': self.label.title()}\n"];
        yield 'a projection through a comprehension' => ["class History:\n    def to_json(self):\n        return {'lines': [asdict(h) for h in self.lines], 'until': self.until}\n"];
        yield 'external names as keys' => ["def headers(length, kind):\n    return {'Content-Length': str(length), 'Content-Type': kind}\n"];
        yield 'not returned' => ["def a(x, y):\n    payload = {'id': x.id, 'page': y.page}\n    send(payload)\n"];
        yield 'integer keys' => ["def a(x, y):\n    return {1: x.a, 2: y.b}\n"];
    }
}
