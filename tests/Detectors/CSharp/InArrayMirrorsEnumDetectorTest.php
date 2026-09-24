<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\CSharp;

use JesseGall\CodeCommandments\Cs\Codebase;
use JesseGall\CodeCommandments\Detectors\CSharp\InArrayMirrorsEnumDetector;
use JesseGall\CodeCommandments\Tests\Cs\NeedsTheBridge;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Membership tested against an inline list of strings that are an enum's member names is the enum
 * written again by hand; a list that names anything else is fine.
 */
final class InArrayMirrorsEnumDetectorTest extends TestCase
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
            'an array literal' => ['public static bool IsDone(string s) => new[] { "paid", "refunded" }.Contains(s);', 1],
            'a list initializer' => ['public static bool IsDone(string s) => new System.Collections.Generic.List<string> { "Paid", "Refunded" }.Contains(s);', 1],
            'a collection expression' => ['public static bool IsDone(string s) => ((string[])["PAID", "OPEN"]).Contains(s);', 1],
            'an is-or pattern' => ['public static bool IsDone(string s) => s is "Paid" or "Refunded";', 1],
            'an is-or pattern of other strings' => ['public static bool IsDone(string s) => s is "paid" or "shipped";', 0],
            'strings that are not all members' => ['public static bool IsDone(string s) => new[] { "paid", "shipped" }.Contains(s);', 0],
            'a single member' => ['public static bool IsPaid(string s) => new[] { "paid" }.Contains(s);', 0],
            'a list with a variable in it' => ['public static bool IsDone(string s, string other) => new[] { "paid", other }.Contains(s);', 0],
        ];
    }

    #[DataProvider('members')]
    public function test_flags_membership_in_strings_that_mirror_an_enum(string $member, int $flagged): void
    {
        $source = "using System.Linq;\npublic enum Status { Open, Paid, Refunded }\npublic static class Orders\n{\n    {$member}\n}\n";
        $this->assertCount($flagged, new InArrayMirrorsEnumDetector()->find(Codebase::fromString($source, 'Orders.cs')), $source);
    }
}
