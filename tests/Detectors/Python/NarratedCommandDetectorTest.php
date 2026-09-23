<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\Python;

use JesseGall\CodeCommandments\Detectors\Python\NarratedCommandDetector;
use JesseGall\CodeCommandments\Python\Detector;
use PHPUnit\Framework\TestCase;

final class NarratedCommandDetectorTest extends TestCase
{
    use ProvesAPythonRule;
    use FlagsEachSnippetOnce;

    private function rule(): Detector
    {
        return new NarratedCommandDetector();
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function thisSin(): iterable
    {
        yield 'returns None' => ["class Panel:\n    def hides(self) -> None:\n        self.visible = False\n"];
        yield 'fluent through Self' => ["from typing import Self\n\nclass Query:\n    def filters(self, term: str) -> Self:\n        self.terms.append(term)\n        return self\n"];
        yield 'fluent through its own class' => ["class Query:\n    def sorts(self, key: str) -> \"Query\":\n        self.key = key\n        return self\n"];
        yield 'a snake_case compound' => ["class Door:\n    def locks_for_night(self) -> None:\n        self.bolted = True\n"];
        yield 'never returns' => ["from typing import NoReturn\n\nclass Guard:\n    def fails(self, why: str) -> NoReturn:\n        raise RuntimeError(why)\n"];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function notThisSin(): iterable
    {
        yield 'an imperative' => ["class Panel:\n    def hide(self) -> None:\n        self.visible = False\n"];
        yield 'answers a value' => ["class Panel:\n    def hides(self) -> bool:\n        return not self.visible\n"];
        yield 'unannotated' => ["class Panel:\n    def hides(self):\n        self.visible = False\n"];
        yield 'a plural noun' => ["class Form:\n    def fields(self) -> None:\n        self._fields.clear()\n"];
        yield 'a static factory named for what it builds' => ["class Query:\n    @staticmethod\n    def filters(term: str) -> \"Query\":\n        return Query()\n"];
        yield 'a fluent relation' => ["class Rule:\n    def starts_with(self, prefix: str) -> \"Rule\":\n        self.prefix = prefix\n        return self\n"];
        yield 'a dunder' => ["class Box:\n    def __init__(self) -> None:\n        self.items = []\n"];
        yield 'an override' => ["class Base:\n    def hides(self) -> None:\n        pass\n\nclass Panel(Base):\n    def hides(self) -> None:\n        self.visible = False\n"];
        yield 'a module function' => ["def sends(message: str) -> None:\n    print(message)\n"];
    }
}
