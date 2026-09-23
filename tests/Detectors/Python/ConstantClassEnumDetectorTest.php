<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\Python;

use JesseGall\CodeCommandments\Detectors\Python\ConstantClassEnumDetector;
use JesseGall\CodeCommandments\Python\Detector;
use PHPUnit\Framework\TestCase;

final class ConstantClassEnumDetectorTest extends TestCase
{
    use ProvesAPythonRule;
    use FlagsEachSnippetOnce;

    private function rule(): Detector
    {
        return new ConstantClassEnumDetector();
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function thisSin(): iterable
    {
        yield 'string constants' => ["class Status:\n    PENDING = 'pending'\n    PAID = 'paid'\n    SHIPPED = 'shipped'\n"];
        yield 'with a docstring' => ["class Level:\n    \"\"\"How loud a message is.\"\"\"\n    LOW = 1\n    HIGH = 2\n"];
        yield 'from object' => ["class Colour(object):\n    RED = '#f00'\n    GREEN = '#0f0'\n"];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function notThisSin(): iterable
    {
        yield 'already an enum' => ["class Status(Enum):\n    PENDING = 'pending'\n    PAID = 'paid'\n"];
        yield 'with a method' => ["class Status:\n    PENDING = 'pending'\n    PAID = 'paid'\n\n    def label(self):\n        return 'x'\n"];
        yield 'annotated fields' => ["class Settings:\n    host: str = 'localhost'\n    port: int = 8080\n"];
        yield 'one constant' => ["class Limits:\n    MAX = 10\n"];
        yield 'documents' => ["class Queries:\n    ORDERS = '''\n        select *\n        from orders\n    '''\n    LINES = '''\n        select *\n        from lines\n    '''\n"];
        yield 'a decorated class' => ["@dataclass\nclass Point:\n    x = 0\n    y = 0\n"];
        yield 'computed values' => ["class Paths:\n    ROOT = Path('/srv')\n    LOGS = ROOT / 'logs'\n"];
    }
}
