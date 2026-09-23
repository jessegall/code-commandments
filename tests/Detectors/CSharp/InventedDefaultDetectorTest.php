<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\CSharp;

use JesseGall\CodeCommandments\Cs\Codebase;
use JesseGall\CodeCommandments\Detectors\CSharp\InventedDefaultDetector;
use JesseGall\CodeCommandments\Tests\Cs\NeedsTheBridge;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * An invented `""`, `0` or `false` is flagged where it fills an argument and where a lookup helper
 * answers a miss with it; a chosen default, a fallback kept in a local, and a helper that throws on a
 * miss are not inventions.
 */
final class InventedDefaultDetectorTest extends TestCase
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
            'a coalesce filling an argument' => ['public void Greet(string? name) => Console.WriteLine(name ?? "");', 1],
            'a null test filling an argument' => ['public void Count(int? units) => Console.WriteLine(units is null ? 0 : units);', 1],
            'string.Empty filling an argument' => ['public void Label(string? name) => Console.WriteLine(name ?? string.Empty);', 1],
            'a helper answering a miss with an empty string' => ['public string Title(string key) => titles.TryGetValue(key, out var title) ? title : "";', 1],
            'a helper returning false on a miss' => ['public bool Flag(string key) { if (flags.TryGetValue(key, out var on)) { return on; } return false; }', 1],
            'a chosen default' => ['public void Pay(string? currency) => Console.WriteLine(currency ?? "EUR");', 0],
            'a fallback kept in a local' => ['public void Show(string? name) { var shown = name ?? ""; Console.WriteLine(shown.Length); }', 0],
            'a helper that throws on a miss' => ['public string Must(string key) => titles.TryGetValue(key, out var title) ? title : throw new KeyNotFoundException(key);', 0],
            'a helper returning something it computed' => ['public string Pad(string key) => key.Length > 3 ? key : "";', 0],
        ];
    }

    #[DataProvider('members')]
    public function test_flags_an_invented_value_standing_in_for_a_missing_one(string $member, int $flagged): void
    {
        $source = "using System;\nusing System.Collections.Generic;\n\npublic class Labels\n{\n    private readonly Dictionary<string, string> titles = new();\n\n    private readonly Dictionary<string, bool> flags = new();\n\n    {$member}\n}\n";

        $this->assertCount($flagged, new InventedDefaultDetector()->find(Codebase::fromString($source, 'Labels.cs')), $source);
    }
}
