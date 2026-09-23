<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\Python;

use JesseGall\CodeCommandments\Detectors\Python\MessageStringRaiseDetector;
use JesseGall\CodeCommandments\Python\Detector;
use PHPUnit\Framework\TestCase;

final class MessageStringRaiseDetectorTest extends TestCase
{
    use ProvesAPythonRule;
    use FlagsEachSnippetOnce;

    private function rule(): Detector
    {
        return new MessageStringRaiseDetector();
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function thisSin(): iterable
    {
        yield 'RuntimeError with a literal' => ["raise RuntimeError('no active request')\n"];
        yield 'Exception with an f-string' => ["raise Exception(f'order {n} is locked')\n"];
        yield 'BaseException, implicitly concatenated' => ["raise BaseException('stop ' 'now')\n"];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function notThisSin(): iterable
    {
        yield 'a specific builtin category' => ["raise ValueError(f'quantity must be positive, got {q}')\n"];
        yield 'a type check' => ["raise TypeError('expected a str')\n"];
        yield 'a named failure' => ["raise OrderLocked.for_(n)\n"];
        yield 'a named failure with a message' => ["raise OrderLocked('locked')\n"];
        yield 'a generic without a message' => ["raise RuntimeError\n"];
        yield 'a generic wrapping a value' => ["raise RuntimeError(reason)\n"];
        yield 'a bare re-raise' => ["try:\n    x()\nexcept Exception:\n    raise\n"];
    }
}
