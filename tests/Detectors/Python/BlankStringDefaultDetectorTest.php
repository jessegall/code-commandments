<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\Python;

use JesseGall\CodeCommandments\Detectors\Python\BlankStringDefaultDetector;
use JesseGall\CodeCommandments\Python\Detector;
use PHPUnit\Framework\TestCase;

final class BlankStringDefaultDetectorTest extends TestCase
{
    use ProvesAPythonRule;
    use FlagsEachSnippetOnce;

    private function rule(): Detector
    {
        return new BlankStringDefaultDetector();
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function thisSin(): iterable
    {
        yield 'compared to the blank' => ["def title(heading: str, strapline: str = '') -> str:\n    if strapline == '':\n        return heading\n    return heading + strapline\n"];
        yield 'asked with not' => ["def greet(name: str = \"\") -> str:\n    if not name:\n        return 'hi'\n    return 'hi ' + name\n"];
        yield 'asked by truthiness' => ["def tag(label: str = '') -> str:\n    return f'[{label}]' if label else ''\n"];
        yield 'a dataclass field its methods ask' => ["class Note:\n    text: str = ''\n\n    def shown(self) -> str:\n        return 'none' if self.text == '' else self.text\n"];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function notThisSin(): iterable
    {
        yield 'an accumulator never asked' => ["def join(parts: list, glue: str = '') -> str:\n    return glue.join(parts)\n"];
        yield 'an optional string' => ["def title(heading: str, strapline: str | None = None) -> str:\n    if strapline is None:\n        return heading\n    return heading + strapline\n"];
        yield 'a real default' => ["def pay(currency: str = 'EUR') -> str:\n    if currency == 'EUR':\n        return 'euro'\n    return currency\n"];
        yield 'another name asked' => ["def show(label: str = '', other: str = 'x') -> str:\n    if not other:\n        return label\n    return other\n"];
        yield 'untyped' => ["def show(label=''):\n    if not label:\n        return 'none'\n    return label\n"];
    }
}
