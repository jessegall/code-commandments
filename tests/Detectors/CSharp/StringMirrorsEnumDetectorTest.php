<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\CSharp;

use JesseGall\CodeCommandments\Cs\Codebase;
use JesseGall\CodeCommandments\Detectors\CSharp\StringMirrorsEnumDetector;
use JesseGall\CodeCommandments\Tests\Cs\NeedsTheBridge;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * A switch or an `if` ladder on strings that spell an enum's members, in any case, is the enum written
 * again as text; strings that are not all members, a single string, and a switch on the enum itself are not.
 */
final class StringMirrorsEnumDetectorTest extends TestCase
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
            'a switch statement on member names' => ['switch (raw) { case "paid": return 1; case "refunded": return 2; default: return 0; }', 1],
            'a switch expression on member names' => ['return raw switch { "Pending" => 1, "Paid" => 2, _ => 0 };', 1],
            'an if ladder on member names' => ['if (raw == "pending") { return 1; } else if (raw == "paid") { return 2; } return 0;', 1],
            'strings that are not all members' => ['switch (raw) { case "paid": return 1; case "lost": return 2; default: return 0; }', 0],
            'a single member name' => ['return raw == "paid" ? 1 : 0;', 0],
            'a switch on the enum itself' => ['return Enum.Parse<Status>(raw, true) switch { Status.Pending => 1, Status.Paid => 2, _ => 0 };', 0],
        ];
    }

    #[DataProvider('bodies')]
    public function test_flags_strings_that_spell_an_enum(string $body, int $flagged): void
    {
        $source = "using System;\n\npublic enum Status { Pending, Paid, Refunded }\n\npublic class Orders\n{\n    public int Rank(string raw)\n    {\n        {$body}\n    }\n}\n";

        $this->assertCount($flagged, new StringMirrorsEnumDetector()->find(Codebase::fromString($source, 'Orders.cs')), $source);
    }
}
