<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\CSharp;

use JesseGall\CodeCommandments\Cs\Codebase;
use JesseGall\CodeCommandments\Detectors\CSharp\ConstClassEnumDetector;
use JesseGall\CodeCommandments\Tests\Cs\NeedsTheBridge;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * A class of nothing but `const` strings or numbers whose constants are compared as cases is a closed set
 * that wants to be an enum; constants only handed on as names, and a class with anything else in it, are
 * left alone.
 */
final class ConstClassEnumDetectorTest extends TestCase
{
    use NeedsTheBridge;

    protected function setUp(): void
    {
        $this->requireTheBridge();
    }

    /**
     * @return array<string, array{string, string, int}>
     */
    public static function sources(): array
    {
        $status = 'public static class Status { public const string Paid = "paid"; public const string Open = "open"; }';

        return [
            'compared with ==' => [$status, 'public static bool IsPaid(string status) => status == Status.Paid;', 1],
            'a case label' => [$status, 'public static int Rank(string status) { switch (status) { case Status.Paid: return 1; default: return 0; } }', 1],
            'a switch expression arm' => [$status, 'public static int Rank(string status) => status switch { Status.Open => 0, _ => 1 };', 1],
            'numbers compared with !=' => ['public static class Tier { public const int Gold = 3; public const int Silver = 2; }', 'public static bool IsBasic(int tier) => tier != Tier.Gold && tier != Tier.Silver;', 1],
            'only handed on as a name' => [$status, 'public static string Label() => string.Join(",", Status.Paid, Status.Open);', 0],
            'a class with a method as well' => ['public static class Status { public const string Paid = "paid"; public const string Open = "open"; public static bool IsKnown(string s) => s == Paid || s == Open; }', 'public static bool IsPaid(string status) => status == Status.Paid;', 0],
            'a single constant' => ['public static class Status { public const string Paid = "paid"; }', 'public static bool IsPaid(string status) => status == Status.Paid;', 0],
        ];
    }

    #[DataProvider('sources')]
    public function test_flags_a_class_of_constants_compared_as_cases(string $constants, string $member, int $flagged): void
    {
        $source = "{$constants}\npublic static class Orders\n{\n    {$member}\n}\n";
        $this->assertCount($flagged, new ConstClassEnumDetector()->find(Codebase::fromString($source, 'Orders.cs')), $source);
    }
}
