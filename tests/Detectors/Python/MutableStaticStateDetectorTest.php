<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\Python;

use JesseGall\CodeCommandments\Detectors\Python\MutableStaticStateDetector;
use JesseGall\CodeCommandments\Python\Detector;
use PHPUnit\Framework\TestCase;

final class MutableStaticStateDetectorTest extends TestCase
{
    use ProvesAPythonRule;
    use FlagsEachSnippetOnce;

    private function rule(): Detector
    {
        return new MutableStaticStateDetector();
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function thisSin(): iterable
    {
        yield 'a global assigned' => ["CURRENT = None\n\ndef switch(env):\n    global CURRENT\n    CURRENT = env\n"];
        yield 'a global counted up' => ["CALLS = 0\n\ndef hit():\n    global CALLS\n    CALLS += 1\n"];
        yield 'a class attribute through cls' => ["class Config:\n    active = None\n\n    @classmethod\n    def use(cls, name):\n        cls.active = name\n"];
        yield 'a class attribute through the class name' => ["class Counter:\n    total = 0\n\n    def add(self, n):\n        Counter.total += n\n"];
        yield 'a class attribute through type(self)' => ["class Registry:\n    last = None\n\n    def note(self, item):\n        type(self).last = item\n"];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function notThisSin(): iterable
    {
        yield 'a local with the same name' => ["CURRENT = None\n\ndef switch(env):\n    CURRENT = env\n    return CURRENT\n"];
        yield 'an instance attribute' => ["class Config:\n    def use(self, name):\n        self.active = name\n"];
        yield 'a module-level assignment' => ["CURRENT = None\nCURRENT = 'prod'\n"];
        yield 'a class body attribute' => ["class Config:\n    active = None\n"];
        yield 'another class written through its name' => ["class Other:\n    total = 0\n\nclass Counter:\n    def add(self, n):\n        self.other.total = n\n"];
        yield 'a memo filled once' => ["_pattern = None\n\ndef pattern():\n    global _pattern\n    if _pattern is None:\n        _pattern = compile_it()\n    return _pattern\n"];
        yield 'a memo filled once, asked with not' => ["_version = ''\n\ndef version():\n    global _version\n    if not _version:\n        _version = read_version()\n    return _version\n"];
        yield 'a memo filled once, asked by length' => ["_map = {}\n\ndef mapping():\n    global _map\n    if len(_map) == 0:\n        _map = load()\n    return _map\n"];
        yield 'a nonlocal' => ["def outer():\n    n = 0\n    def inner():\n        nonlocal n\n        n += 1\n    return inner\n"];
    }
}
