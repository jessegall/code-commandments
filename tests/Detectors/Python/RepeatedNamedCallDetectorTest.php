<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\Python;

use JesseGall\CodeCommandments\Detectors\Python\RepeatedNamedCallDetector;
use JesseGall\CodeCommandments\Python\Detector;
use PHPUnit\Framework\TestCase;

final class RepeatedNamedCallDetectorTest extends TestCase
{
    use ProvesARecurringPythonRule;

    private const string NODE = "class Node:\n    def copy_with(self, **changes):\n        return Node()\n\n\n";

    private function rule(): Detector
    {
        return new RepeatedNamedCallDetector();
    }

    /**
     * @return iterable<string, array{string, list<int>}>
     */
    public static function recurring(): iterable
    {
        yield 'a built value under one keyword' => [self::NODE . "def a(node: Node):\n    return node.copy_with(meta=Payload.of(1).to_dict())\n\n\ndef b(node: Node):\n    return node.copy_with(meta=Other.of(2).to_dict())\n", [7, 11]];
        yield 'a dict literal under one keyword' => [self::NODE . "def a(node: Node):\n    return node.copy_with(style={'bold': True})\n\n\ndef b(node: Node):\n    return node.copy_with(style={'italic': True})\n", [7, 11]];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function notThisSin(): iterable
    {
        yield 'written once' => [self::NODE . "def a(node: Node):\n    return node.copy_with(meta=Payload.of(1).to_dict())\n"];
        yield 'different keywords' => [self::NODE . "def a(node: Node):\n    return node.copy_with(meta=Payload.of().to_dict())\n\n\ndef b(node: Node):\n    return node.copy_with(label=Payload.of().to_dict())\n"];
        yield 'plain values, no construction' => [self::NODE . "def a(node: Node, x):\n    return node.copy_with(label=x)\n\n\ndef b(node: Node, y):\n    return node.copy_with(label=y)\n"];
        yield 'a bare function call under the keyword' => [self::NODE . "def a(node: Node):\n    return node.copy_with(help=_('show help'))\n\n\ndef b(node: Node):\n    return node.copy_with(help=_('show version'))\n"];
        yield 'different value shapes' => [self::NODE . "def a(node: Node):\n    return node.copy_with(meta=Payload.of().to_dict())\n\n\ndef b(node: Node):\n    return node.copy_with(meta={'port': 1})\n"];
        yield 'no keyword rest to take it' => ["class Node:\n    def copy_with(self, meta=None):\n        return Node()\n\n\ndef a(node: Node):\n    return node.copy_with(meta=Payload.of().to_dict())\n\n\ndef b(node: Node):\n    return node.copy_with(meta=Payload.of().to_dict())\n"];
        yield 'an unresolved receiver' => [self::NODE . "def a(node):\n    return node.copy_with(meta=Payload.of().to_dict())\n\n\ndef b(node):\n    return node.copy_with(meta=Payload.of().to_dict())\n"];
    }
}
