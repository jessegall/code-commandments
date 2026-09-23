<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\Python;

use JesseGall\CodeCommandments\Detectors\Python\ShortCircuitStatementDetector;
use JesseGall\CodeCommandments\Python\Detector;
use PHPUnit\Framework\TestCase;

final class ShortCircuitStatementDetectorTest extends TestCase
{
    use ProvesAPythonRule;
    use FlagsEachSnippetOnce;

    private function rule(): Detector
    {
        return new ShortCircuitStatementDetector();
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function thisSin(): iterable
    {
        yield 'an and' => ["def a(node):\n    node.built and node.forget()\n"];
        yield 'an or' => ["def a(cache, key):\n    cache.has(key) or cache.warm(key)\n"];
        yield 'a chain of them' => ["def a(x):\n    x.ok and x.ready and x.go()\n"];
        yield 'at module level' => ["DEBUG and configure_logging()\n"];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function notThisSin(): iterable
    {
        yield 'the result is assigned' => ["def a(x):\n    ok = x.ready and x.go()\n"];
        yield 'the result is returned' => ["def a(x):\n    return x.ready or x.fallback()\n"];
        yield 'an if' => ["def a(node):\n    if node.built:\n        node.forget()\n"];
        yield 'a call on its own' => ["def a(node):\n    node.forget()\n"];
        yield 'inside a call' => ["def a(x):\n    log(x.ok and x.name)\n"];
    }
}
