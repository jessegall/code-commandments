<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\CSharp;

use JesseGall\CodeCommandments\Cs\Codebase;
use JesseGall\CodeCommandments\Detectors\CSharp\MemberAfterMethodDetector;
use JesseGall\CodeCommandments\Tests\Cs\NeedsTheBridge;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * A field, constant or stored property declared below a constructor or a method hides the type's state among
 * its behaviour; state at the top, and a computed property among the methods, are fine.
 */
final class MemberAfterMethodDetectorTest extends TestCase
{
    use NeedsTheBridge;

    protected function setUp(): void
    {
        $this->requireTheBridge();
    }

    /**
     * @return array<string, array{string, int}>
     */
    public static function bodies(): array
    {
        return [
            'a field below a method' => ['public int Next() => count + 1; private int count;', 1],
            'a constant below the constructor' => ['public Client() { } private const int Retries = 3;', 1],
            'an auto-property below a method' => ['public void Reset() { } public string Name { get; init; } = "";', 1],
            'state at the top' => ['private const int Retries = 3; private int count; public string Name { get; init; } = ""; public Client() { } public int Next() => count + 1;', 0],
            'an interface\'s property after a method' => ['public interface IRenderer { void Render(); bool CanRender { get; } }', 0],
            'an abstract property after a method' => ['public abstract class Shape { public abstract double Area(); public abstract string Kind { get; } }', 0],
            'a computed property among the methods' => ['private int count; public int Next() => count + 1; public bool IsEmpty => count == 0;', 0],
        ];
    }

    #[DataProvider('bodies')]
    public function test_flags_state_declared_below_behaviour(string $body, int $flagged): void
    {
        $source = "public sealed class Client\n{\n    {$body}\n}\n";
        $this->assertCount($flagged, new MemberAfterMethodDetector()->find(Codebase::fromString($source, 'Client.cs')), $source);
    }
}
