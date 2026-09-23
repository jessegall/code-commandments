<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\Python;

use JesseGall\CodeCommandments\Detectors\Python\ArchaeologyCommentDetector;
use JesseGall\CodeCommandments\Python\Detector;
use PHPUnit\Framework\TestCase;

final class ArchaeologyCommentDetectorTest extends TestCase
{
    use ProvesAPythonRule;
    use FlagsEachSnippetOnce;

    private function rule(): Detector
    {
        return new ArchaeologyCommentDetector();
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function thisSin(): iterable
    {
        yield 'a comment above a function' => ["# formerly lived in checkout.py\ndef price(order):\n    return order.total\n"];
        yield 'a comment above a statement' => ["def price(order):\n    # used to be a dict lookup\n    return order.total\n"];
        yield 'a docstring' => ["class Cart:\n    \"\"\"The basket; refactored from the old session helper.\"\"\"\n\n    lines = []\n"];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function notThisSin(): iterable
    {
        yield 'a comment saying why' => ["def price(order):\n    # a refund carries no id of its own, so the charge reference names the row\n    return order.total\n"];
        yield 'runtime state, not history' => ["def price(order):\n    # the item no longer exists once the order ships\n    return order.total\n"];
        yield 'a passive use' => ["class Cookie:\n    \"\"\"Carries a coded value, which is used to hold the wire form.\"\"\"\n\n    value = ''\n"];
        yield 'a formerly-something noun' => ["def done(queue):\n    \"\"\"Mark a formerly enqueued task complete.\"\"\"\n    queue.pop()\n"];
        yield 'a Sphinx version note' => ["def dispatch(self):\n    \"\"\"Dispatch the request.\n\n    .. versionchanged:: 0.7\n        Renamed from ``handle``; it no longer does the exception handling.\n    \"\"\"\n    return self.run()\n"];
        yield 'a Sphinx version note in attribute comments' => ["class App:\n    #: The globals class.\n    #:\n    #: .. versionadded:: 0.10\n    #:     Renamed from ``request_globals_class``.\n    globals_class = dict\n"];
        yield 'a runtime origin across a line break' => ["class DocTest:\n    \"\"\"Examples run together.\n\n    - filename: the file this DocTest was extracted\n      from, or None.\n    \"\"\"\n\n    examples = []\n"];
        yield 'a present-tense docstring' => ["def price(order):\n    \"\"\"The order's total in cents.\"\"\"\n    return order.total\n"];
    }
}
