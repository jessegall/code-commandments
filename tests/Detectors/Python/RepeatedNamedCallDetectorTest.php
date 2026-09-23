<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\Python;

use JesseGall\CodeCommandments\Detectors\Python\RepeatedNamedCallDetector;
use JesseGall\CodeCommandments\Py\Codebase;
use JesseGall\CodeCommandments\Py\ExprMatch;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RepeatedNamedCallDetectorTest extends TestCase
{
    private const string NODE = "class Node:\n    def copy_with(self, **changes):\n        return Node()\n\n\n";

    /**
     * @param  list<int>  $lines
     */
    #[DataProvider('recurring')]
    public function test_flags_every_site_of_one_keyword_construction(string $source, array $lines): void
    {
        $this->assertSame($lines, $this->linesIn(self::NODE . $source));
    }

    /**
     * @return iterable<string, array{string, list<int>}>
     */
    public static function recurring(): iterable
    {
        yield 'a built value under one keyword' => ["def a(node: Node):\n    return node.copy_with(meta=Payload(port=1).to_dict())\n\n\ndef b(node: Node):\n    return node.copy_with(meta=Other(port=2).to_dict())\n", [7, 11]];
        yield 'a dict literal under one keyword' => ["def a(node: Node):\n    return node.copy_with(style={'bold': True})\n\n\ndef b(node: Node):\n    return node.copy_with(style={'italic': True})\n", [7, 11]];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function notRecurring(): iterable
    {
        yield 'written once' => ["def a(node: Node):\n    return node.copy_with(meta=Payload(port=1).to_dict())\n"];
        yield 'different keywords' => ["def a(node: Node):\n    return node.copy_with(meta=Payload().to_dict())\n\n\ndef b(node: Node):\n    return node.copy_with(label=Payload().to_dict())\n"];
        yield 'plain values, no construction' => ["def a(node: Node, x):\n    return node.copy_with(label=x)\n\n\ndef b(node: Node, y):\n    return node.copy_with(label=y)\n"];
        yield 'a bare function call under the keyword' => ["def a(node: Node):\n    return node.copy_with(help=_('show help'))\n\n\ndef b(node: Node):\n    return node.copy_with(help=_('show version'))\n"];
        yield 'different value shapes' => ["def a(node: Node):\n    return node.copy_with(meta=Payload().to_dict())\n\n\ndef b(node: Node):\n    return node.copy_with(meta={'port': 1})\n"];
        yield 'an unresolved receiver' => ["def a(node):\n    return node.copy_with(meta=Payload().to_dict())\n\n\ndef b(node):\n    return node.copy_with(meta=Payload().to_dict())\n"];
    }

    #[DataProvider('notRecurring')]
    public function test_leaves_what_is_not_one_repeated_construction(string $source): void
    {
        $this->assertSame([], $this->linesIn(self::NODE . $source));
    }

    public function test_leaves_a_function_without_keyword_rest(): void
    {
        $source = "class Node:\n    def copy_with(self, meta=None):\n        return Node()\n\n\ndef a(node: Node):\n    return node.copy_with(meta=Payload().to_dict())\n\n\ndef b(node: Node):\n    return node.copy_with(meta=Payload().to_dict())\n";

        $this->assertSame([], $this->linesIn($source));
    }

    /**
     * @return list<int>
     */
    private function linesIn(string $source): array
    {
        return array_map(static fn (ExprMatch $match): int => $match->line(), new RepeatedNamedCallDetector()->find(Codebase::fromString($source)));
    }
}
