<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\CSharp;

use JesseGall\CodeCommandments\Cs\Codebase;
use JesseGall\CodeCommandments\Detectors\CSharp\PositionalTupleReturnDetector;
use JesseGall\CodeCommandments\Tests\Cs\NeedsTheBridge;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * A member whose declared result is a tuple with unnamed slots hands its caller `Item1`, `Item2` to read
 * by position; a tuple that names its slots, and a contract's own signature, are fine.
 */
final class PositionalTupleReturnDetectorTest extends TestCase
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
            'an unnamed tuple' => ['public (decimal, decimal, string) Totals() => (1m, 2m, "EUR");', 1],
            'an async unnamed tuple' => ['public async System.Threading.Tasks.Task<(int, int)> LoadAsync() { await System.Threading.Tasks.Task.Yield(); return (1, 2); }', 1],
            'a nullable unnamed tuple' => ['public (int, int)? Span() => null;', 1],
            'a local function' => ['public int Sum() { (int, int) Pair() => (1, 2); var (a, b) = Pair(); return a + b; }', 1],
            'slots of different types' => ['public (Ledger, string) Opened() => (this, "EUR");', 0],
            'a tuple that names its slots' => ['public (decimal Net, decimal Vat, string Currency) Totals() => (1m, 2m, "EUR");', 0],
            'a tuple taken as a parameter' => ['public int Sum((int, int) pair) => pair.Item1 + pair.Item2;', 0],
            'an interface and its implementation, reported at the interface' => ['public interface IRange { (int, int) Span(); } public sealed class Range : IRange { public (int, int) Span() => (0, 1); }', 1],
        ];
    }

    #[DataProvider('members')]
    public function test_flags_a_result_read_by_position(string $member, int $flagged): void
    {
        $source = "public class Ledger\n{\n    {$member}\n}\n";
        $this->assertCount($flagged, new PositionalTupleReturnDetector()->find(Codebase::fromString($source, 'Ledger.cs')), $source);
    }
}
