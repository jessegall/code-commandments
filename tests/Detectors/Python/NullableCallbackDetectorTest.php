<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\Python;

use JesseGall\CodeCommandments\Detectors\Python\NullableCallbackDetector;
use JesseGall\CodeCommandments\Python\Detector;
use PHPUnit\Framework\TestCase;

final class NullableCallbackDetectorTest extends TestCase
{
    use ProvesAPythonRule;
    use FlagsEachSnippetOnce;

    private function rule(): Detector
    {
        return new NullableCallbackDetector();
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function thisSin(): iterable
    {
        yield 'asked is not None' => ["def run(work, on_retry: Callable[[int], None] | None = None):\n    if on_retry is not None:\n        on_retry(1)\n    return work()\n"];
        yield 'asked by truthiness' => ["def run(work, progress: Optional[Callable] = None):\n    for step in work:\n        if progress:\n            progress(step)\n"];
        yield 'defaulted with or' => ["def run(work, log: Callable | None = None):\n    (log or print)('start')\n    return work()\n"];
        yield 'filled in the body' => ["def run(work, done: Callable[[], None] | None = None):\n    if done is None:\n        done = lambda: None\n    work()\n    done()\n"];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function notThisSin(): iterable
    {
        yield 'a no-op default' => ["def run(work, on_retry: Callable[[int], None] = lambda n: None):\n    on_retry(1)\n    return work()\n"];
        yield 'a callback passed on untouched' => ["def run(work, on_retry: Callable | None = None):\n    return retry(work, on_retry)\n"];
        yield 'an optional value, not a callback' => ["def run(work, name: str | None = None):\n    if name is not None:\n        print(name)\n"];
        yield 'a required callback' => ["def run(work, done: Callable):\n    work()\n    done()\n"];
    }
}
