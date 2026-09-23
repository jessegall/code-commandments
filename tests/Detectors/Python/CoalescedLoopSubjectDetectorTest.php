<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\Python;

use JesseGall\CodeCommandments\Detectors\Python\CoalescedLoopSubjectDetector;
use JesseGall\CodeCommandments\Python\Detector;
use PHPUnit\Framework\TestCase;

final class CoalescedLoopSubjectDetectorTest extends TestCase
{
    use ProvesAPythonRule;
    use FlagsEachSnippetOnce;

    private function rule(): Detector
    {
        return new CoalescedLoopSubjectDetector();
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function thisSin(): iterable
    {
        yield 'a keyed default' => ["def a(below, id):\n    for child in below.get(id, []):\n        visit(child)\n"];
        yield 'an or-empty' => ["def a(rows):\n    for row in rows or []:\n        save(row)\n"];
        yield 'a conditional default' => ["def a(tags):\n    for tag in tags if tags is not None else ():\n        print(tag)\n"];
        yield 'reached through a parameter' => ["def a(order):\n    for line in order.lines or []:\n        line.ship()\n"];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function notThisSin(): iterable
    {
        yield 'its own state, a sparse registry' => ["class R:\n    def a(self, id):\n        for listener in self.listeners.get(id, []):\n            listener()\n"];
        yield 'a normalised call result' => ["def a():\n    for path in glob('*') or []:\n        read(path)\n"];
        yield 'a local it computed' => ["def a():\n    found = search()\n    for hit in found or []:\n        show(hit)\n"];
        yield 'a real fallback collection' => ["def a(rows, defaults):\n    for row in rows or defaults:\n        save(row)\n"];
        yield 'a plain loop' => ["def a(rows):\n    for row in rows:\n        save(row)\n"];
        yield 'a keyed read with no default' => ["def a(below, id):\n    for child in below.get(id):\n        visit(child)\n"];
    }
}
