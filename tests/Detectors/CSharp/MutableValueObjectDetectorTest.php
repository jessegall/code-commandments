<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\CSharp;

use JesseGall\CodeCommandments\Cs\Codebase;
use JesseGall\CodeCommandments\Detectors\CSharp\MutableValueObjectDetector;
use JesseGall\CodeCommandments\Tests\Cs\NeedsTheBridge;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * A record that can change after it is built — a `set` accessor, or a method writing its own state — is a
 * value that two holders can see differently; `init`, `with` and a class's own writes are fine.
 */
final class MutableValueObjectDetectorTest extends TestCase
{
    use NeedsTheBridge;

    protected function setUp(): void
    {
        $this->requireTheBridge();
    }

    /**
     * @return array<string, array{string, int}>
     */
    public static function sources(): array
    {
        return [
            'a set accessor' => ['public sealed record Cart { public int Items { get; set; } }', 1],
            'a method stepping a property' => ['public sealed record Cart(int Items) { public int Items { get; private set; } = Items; public void Add() { Items++; } }', 2],
            'a method writing a field through this' => ['public record struct Meter { private int reading; public void Tick(int by) { this.reading += by; } }', 1],
            'init only' => ['public sealed record Cart { public int Items { get; init; } }', 0],
            'a with expression' => ['public sealed record Cart(int Items) { public Cart Added() => this with { Items = Items + 1 }; }', 0],
            'written in the constructor' => ['public sealed record Cart { private readonly int items; public Cart(int n) { items = n; } }', 0],
            'an init accessor writing its field' => ['public sealed record Cart { private readonly int items; public int Items { get => items; init => items = value; } }', 0],
            'a lazily filled cache' => ['public sealed record Cart(int[] Items) { private int? total; public int Total => total ??= Items.Length; }', 0],
            'a setter an interface demands' => ['public interface IStamped { long At { get; set; } } public sealed record Cart : IStamped { private long at; long IStamped.At { get => at; set => at = value; } }', 0],
            'a class, not a record' => ['public sealed class Cart { public int Items { get; set; } public void Add() { Items++; } }', 0],
            'a local that shadows a member' => ['public sealed record Cart(int Items) { public int Doubled() { var Items = this.Items; Items *= 2; return Items; } }', 0],
        ];
    }

    #[DataProvider('sources')]
    public function test_flags_a_record_that_changes_after_construction(string $source, int $flagged): void
    {
        $this->assertCount($flagged, new MutableValueObjectDetector()->find(Codebase::fromString($source, 'Cart.cs')), $source);
    }
}
