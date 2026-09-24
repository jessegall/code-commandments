<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\CSharp;

use JesseGall\CodeCommandments\Cs\Codebase;
use JesseGall\CodeCommandments\Detectors\CSharp\MatchDefaultReturnsNullDetector;
use JesseGall\CodeCommandments\Tests\Cs\NeedsTheBridge;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * A switch over an enum that names every member, whose fallback answers `null` instead of throwing: the
 * fallback is reached only by a value no member names, and answering it hides the bug. A fallback that
 * covers members the switch leaves out is a real answer, and is fine.
 */
final class MatchDefaultReturnsNullDetectorTest extends TestCase
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
            'a switch expression answering null' => ['public static string? Label(Status s) => s switch { Status.Open => "open", Status.Paid => "paid", _ => null };', 1],
            'a switch statement returning default' => ['public static string? Label(Status s) { switch (s) { case Status.Open: return "open"; case Status.Paid: return "paid"; default: return default; } }', 1],
            'an or pattern naming every member, answering false' => ['public static bool Known(Status s) => s switch { Status.Open or Status.Paid => true, _ => false };', 1],
            'a Try method reporting failure' => ['public static bool TryLabel(Status s, out string label) { label = ""; switch (s) { case Status.Open: label = "open"; return true; case Status.Paid: label = "paid"; return true; default: return false; } }', 0],
            'a fallback that throws' => ['public static string Label(Status s) => s switch { Status.Open => "open", Status.Paid => "paid", _ => throw new System.ArgumentOutOfRangeException(nameof(s)) };', 0],
            'a fallback covering a member left out' => ['public static string? Label(Status s) => s switch { Status.Paid => "paid", _ => null };', 0],
            'a guarded arm that does not count' => ['public static string? Label(Status s, bool vip) => s switch { Status.Open => "open", Status.Paid when vip => "paid", _ => null };', 0],
        ];
    }

    #[DataProvider('members')]
    public function test_flags_a_null_fallback_behind_every_member(string $member, int $flagged): void
    {
        $source = "public enum Status { Open, Paid }\npublic static class Orders\n{\n    {$member}\n}\n";
        $this->assertCount($flagged, new MatchDefaultReturnsNullDetector()->find(Codebase::fromString($source, 'Orders.cs')), $source);
    }
}
