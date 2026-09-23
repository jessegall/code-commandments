<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\CSharp;

use JesseGall\CodeCommandments\Cs\Codebase;
use JesseGall\CodeCommandments\Detectors\CSharp\BlankStringDefaultDetector;
use JesseGall\CodeCommandments\Tests\Cs\NeedsTheBridge;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * A `string` defaulted to `""` and then asked whether it is blank is absence wearing a total type; a blank
 * default nothing asks about, a real default and a number are not.
 */
final class BlankStringDefaultDetectorTest extends TestCase
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
            'a parameter compared with ""' => ['public static string Of(string heading, string strapline = "") { if (strapline == "") { return heading; } return heading + strapline; }', 1],
            'a parameter asked IsNullOrEmpty' => ['public static string Of(string heading, string strapline = "") => string.IsNullOrEmpty(strapline) ? heading : heading + strapline;', 1],
            'a parameter asked for its Length' => ['public static string Of(string heading, string strapline = "") => strapline.Length == 0 ? heading : heading + strapline;', 1],
            'a property asked whether it is blank' => ['public string Note { get; init; } = ""; public bool HasNote() => Note != "";', 1],
            'a blank default never asked about' => ['public static string Join(string[] parts, string separator = "") => string.Join(separator, parts);', 0],
            'a real default' => ['public static string Price(decimal amount, string currency = "EUR") => currency == "" ? "" : $"{amount} {currency}";', 0],
            'a number defaulted to zero' => ['public static int Pad(int width = 0) => width == 0 ? 1 : width;', 0],
        ];
    }

    #[DataProvider('members')]
    public function test_flags_a_blank_default_that_is_asked_whether_it_is_blank(string $member, int $flagged): void
    {
        $source = "using System;\n\npublic class Card\n{\n    {$member}\n}\n";
        $this->assertCount($flagged, new BlankStringDefaultDetector()->find(Codebase::fromString($source, 'Card.cs')), $source);
    }
}
