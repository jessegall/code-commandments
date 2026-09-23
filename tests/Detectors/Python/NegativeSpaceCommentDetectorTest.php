<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\Python;

use JesseGall\CodeCommandments\Detectors\Python\NegativeSpaceCommentDetector;
use JesseGall\CodeCommandments\Python\Detector;
use PHPUnit\Framework\TestCase;

final class NegativeSpaceCommentDetectorTest extends TestCase
{
    use ProvesAPythonRule;
    use FlagsEachSnippetOnce;

    private function rule(): Detector
    {
        return new NegativeSpaceCommentDetector();
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function thisSin(): iterable
    {
        yield 'a comment above a statement' => ["def delay(attempt):\n    # the jitter is not random, it follows the retry count\n    return attempt * 3\n"];
        yield 'a docstring defending an absence' => ["class Registry:\n    \"\"\"Handlers by name; the fallback is deliberately not listed here.\"\"\"\n\n    handlers = {}\n"];
        yield 'a comment above a function' => ["# no magic here, just a lookup\ndef rate(zone):\n    return RATES[zone]\n"];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function notThisSin(): iterable
    {
        yield 'a comment saying what it is' => ["def delay(attempt):\n    # the jitter follows the retry count\n    return attempt * 3\n"];
        yield 'an adjective on a content noun' => ["class SystemRandom:\n    def seed(self):\n        \"\"\"Stub method; not used for a system random number generator.\"\"\"\n        return None\n"];
        yield 'a negation in another clause' => ["def make_uuid(node=None):\n    \"\"\"When a node is not given, a pseudo-random one is used.\"\"\"\n    return node\n"];
        yield 'a runtime negation' => ["def ship(order):\n    # an order that is not paid is held back\n    return order.paid\n"];
    }
}
