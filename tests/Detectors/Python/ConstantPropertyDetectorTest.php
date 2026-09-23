<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\Python;

use JesseGall\CodeCommandments\Detectors\Python\ConstantPropertyDetector;
use JesseGall\CodeCommandments\Python\Detector;
use PHPUnit\Framework\TestCase;

final class ConstantPropertyDetectorTest extends TestCase
{
    use ProvesAPythonRule;
    use FlagsEachSnippetOnce;

    private function rule(): Detector
    {
        return new ConstantPropertyDetector();
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function thisSin(): iterable
    {
        yield 'a string' => ["class Box:\n    @property\n    def kind(self) -> str:\n        return 'box'\n"];
        yield 'a computed constant' => ["class Limits:\n    @property\n    def seconds(self) -> int:\n        return 60 * 60\n"];
        yield 'a value built from literals' => ["class Price:\n    @property\n    def zero(self):\n        return Money(0, 'EUR')\n"];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function notThisSin(): iterable
    {
        yield 'derived from self' => ["class Box:\n    @property\n    def volume(self):\n        return self.w * self.h * self.d\n"];
        yield 'an override' => ["class Parcel:\n    @property\n    def kind(self):\n        return 'parcel'\n\nclass Box(Parcel):\n    @property\n    def kind(self):\n        return 'box'\n"];
        yield 'abstract' => ["class Parcel:\n    @property\n    @abstractmethod\n    def kind(self):\n        ...\n"];
        yield 'with a setter' => ["class Box:\n    @property\n    def kind(self):\n        return 'box'\n\n    @kind.setter\n    def kind(self, value):\n        self._kind = value\n"];
        yield 'a plain method' => ["class Box:\n    def kind(self):\n        return 'box'\n"];
        yield 'a base from outside the codebase' => ["class Response(BaseResponse):\n    @property\n    def max_cookie_size(self):\n        return 4093\n"];
        yield 'a lazily computed value' => ["class Machine:\n    @cached_property\n    def processor(self):\n        return detect_processor()\n"];
        yield 'live module state' => ["import sys\n\nclass Handler:\n    @property\n    def stream(self):\n        return sys.stderr\n"];
        yield 'a type(self) read' => ["class Box:\n    @property\n    def label(self):\n        return type(self).__name__\n"];
    }
}
