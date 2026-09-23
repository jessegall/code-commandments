<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\CSharp;

use JesseGall\CodeCommandments\Cs\Codebase;
use JesseGall\CodeCommandments\Detectors\CSharp\EnumCaseOrChainDetector;
use JesseGall\CodeCommandments\Tests\Cs\NeedsTheBridge;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Two or more cases of one enum the codebase declares, tested together at a call site — by `||` or by an
 * `is … or …` pattern — is a group that belongs on the enum; a switch arm and a single case are fine.
 */
final class EnumCaseOrChainDetectorTest extends TestCase
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
            'an || chain of ==' => ['public static bool IsDone(Status s) => s == Status.Paid || s == Status.Refunded;', 1],
            'a three-case chain, reported once' => ['public static bool IsOpen(Status s) => s == Status.Pending || s == Status.Paid || s == Status.Refunded;', 1],
            'an is-or pattern' => ['public static bool IsDone(Status s) => s is Status.Paid or Status.Refunded;', 1],
            'a single case' => ['public static bool IsPaid(Status s) => s == Status.Paid;', 0],
            'cases of an enum the codebase does not declare' => ['public static bool IsWeekend(System.DayOfWeek d) => d == System.DayOfWeek.Saturday || d == System.DayOfWeek.Sunday;', 0],
            'a switch arm' => ['public static int Rank(Status s) => s switch { Status.Paid or Status.Refunded => 1, _ => 0 };', 0],
            'one case of two values' => ['public static bool EitherPaid(Status a, Status b) => a == Status.Paid || b == Status.Paid;', 0],
            'one case and something else' => ['public static bool IsDue(Status s, bool late) => s == Status.Pending || late;', 0],
        ];
    }

    #[DataProvider('members')]
    public function test_flags_enum_cases_tested_as_a_group(string $member, int $flagged): void
    {
        $source = "public enum Status { Pending, Paid, Refunded }\npublic static class Orders\n{\n    {$member}\n}\n";
        $this->assertCount($flagged, new EnumCaseOrChainDetector()->find(Codebase::fromString($source, 'Orders.cs')), $source);
    }
}
