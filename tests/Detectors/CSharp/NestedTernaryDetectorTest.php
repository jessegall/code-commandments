<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\CSharp;

use JesseGall\CodeCommandments\Cs\Codebase;
use JesseGall\CodeCommandments\Detectors\CSharp\NestedTernaryDetector;
use JesseGall\CodeCommandments\Tests\Cs\NeedsTheBridge;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * A conditional expression whose branch is another conditional hides branching in one expression; a single
 * conditional and a switch expression are fine.
 */
final class NestedTernaryDetectorTest extends TestCase
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
            'nested in the false branch' => ['public static string Size(int grams) => grams < 100 ? "small" : grams < 1000 ? "medium" : "large";', 1],
            'nested in parentheses in the true branch' => ['public static string Size(int grams) => grams > 0 ? (grams > 1000 ? "heavy" : "light") : "none";', 1],
            'a single conditional' => ['public static string Size(int grams) => grams < 100 ? "small" : "large";', 0],
            'a switch expression' => ['public static string Size(int grams) => grams switch { < 100 => "small", < 1000 => "medium", _ => "large" };', 0],
        ];
    }

    #[DataProvider('members')]
    public function test_flags_a_conditional_inside_a_conditional(string $member, int $flagged): void
    {
        $source = "public class Parcels\n{\n    {$member}\n}\n";
        $this->assertCount($flagged, new NestedTernaryDetector()->find(Codebase::fromString($source, 'Parcels.cs')), $source);
    }
}
