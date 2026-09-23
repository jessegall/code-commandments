<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\CSharp;

use JesseGall\CodeCommandments\Cs\Codebase;
use JesseGall\CodeCommandments\Detectors\CSharp\MutableStaticStateDetector;
use JesseGall\CodeCommandments\Tests\Cs\NeedsTheBridge;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * A static field written from a method is state no instance owns, changed by whoever ran last. A readonly
 * or const static, one set in the static constructor, and an instance field are not.
 */
final class MutableStaticStateDetectorTest extends TestCase
{
    use NeedsTheBridge;

    protected function setUp(): void
    {
        $this->requireTheBridge();
    }

    /**
     * @return array<string, array{string, int}>
     */
    public static function members(): array
    {
        return [
            'a counter bumped from a method' => ['private static int hits; public void Hit() { hits++; }', 1],
            'a field set from a method' => ['private static string? last; public void Remember(string name) => last = name;', 1],
            'a lazily filled cache' => ['private static string? cached; public string Name() => cached ??= "shop";', 1],
            'written through the class name' => ['public static int Total; public void Add(int amount) { Counter.Total += amount; }', 1],
            'a readonly static' => ['private static readonly string Name = "shop"; public string Get() => Name;', 0],
            'set in the static constructor' => ['private static int start; static Counter() { start = 1; } public int Start() => start;', 0],
            'an instance field' => ['private int hits; public void Hit() { hits++; }', 0],
        ];
    }

    #[DataProvider('members')]
    public function test_flags_a_static_field_written_from_a_method(string $members, int $flagged): void
    {
        $source = "using System;\n\npublic class Counter\n{\n    {$members}\n}\n";
        $this->assertCount($flagged, new MutableStaticStateDetector()->find(Codebase::fromString($source, 'Counter.cs')), $source);
    }
}
