<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\CSharp;

use JesseGall\CodeCommandments\Cs\Codebase;
use JesseGall\CodeCommandments\Cs\NodeMatch;
use JesseGall\CodeCommandments\Detectors\CSharp\DeepNestingDetector;
use JesseGall\CodeCommandments\Tests\Cs\NeedsTheBridge;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The fourth choice deep inside one C# method is flagged, once, where the arrow crosses the limit. An
 * `else if` continues its ladder, `try`/`using`/`lock` are boundaries, and a lambda or local function
 * starts its own count.
 */
final class DeepNestingDetectorTest extends TestCase
{
    use NeedsTheBridge;

    protected function setUp(): void
    {
        $this->requireTheBridge();
    }

    /**
     * @return array<string, array{string, list<string>}>
     */
    public static function bodies(): array
    {
        return [
            'a loop in a loop in an if in a loop' => ["foreach (var a in xs) { if (a > 0) { foreach (var b in xs) { while (b > a) { b--; } } } }", ['WhileStatement']],
            'only the fourth level, once per arrow' => ["foreach (var a in xs) { if (a > 0) { foreach (var b in xs) { if (b > 1) { if (b > 2) { b++; } } } } }", ['IfStatement']],
            'three levels' => ["foreach (var a in xs) { if (a > 0) { foreach (var b in xs) { b++; } } }", []],
            'an else-if ladder is one level' => ["foreach (var a in xs) { if (a == 1) { a++; } else if (a == 2) { a--; } else if (a == 3) { if (a > 0) { a++; } } }", []],
            'an else block is inside the choice' => ["foreach (var a in xs) { if (a == 1) { a++; } else { foreach (var b in xs) { if (b > a) { b++; } } } }", ['IfStatement']],
            'try and using are boundaries' => ["foreach (var a in xs) { try { using (var s = new System.IO.MemoryStream()) { if (a > 0) { lock (xs) { if (a > 1) { a++; } } } } } catch { } }", []],
            'a lambda starts its own count' => ["foreach (var a in xs) { if (a > 0) { foreach (var b in xs) { System.Action f = () => { if (b > 0) { b++; } }; } } }", []],
        ];
    }

    /**
     * @param  list<string>  $flagged  the kinds of construct the rule flags
     */
    #[DataProvider('bodies')]
    public function test_flags_the_choice_that_opens_the_fourth_level(string $body, array $flagged): void
    {
        $source = "using System.Collections.Generic;\n\npublic class Walker\n{\n    public void Walk(List<int> xs)\n    {\n        " . str_replace('{ ', "{\n", $body) . "\n    }\n}\n";
        $found = array_map(static fn (NodeMatch $match): string => $match->node->kind, new DeepNestingDetector()->find(Codebase::fromString($source, 'Walker.cs')));

        $this->assertSame($flagged, $found, $source);
    }
}
