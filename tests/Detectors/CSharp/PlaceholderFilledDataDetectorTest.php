<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\CSharp;

use JesseGall\CodeCommandments\Cs\Codebase;
use JesseGall\CodeCommandments\Detectors\CSharp\PlaceholderFilledDataDetector;
use JesseGall\CodeCommandments\Tests\Cs\NeedsTheBridge;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * A record built with `""` in a slot typed as a required `string` hides a missing value no type can
 * catch; a blank handed to a `string?` slot, or a real value, is fine.
 */
final class PlaceholderFilledDataDetectorTest extends TestCase
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
            'a positional blank' => ['public static Card Make(string title) => new Card(title, "");', 1],
            'string.Empty by position' => ['public static Card Make(string title) => new(title, string.Empty);', 1],
            'a blank in an initializer' => ['public static Note Make() => new Note { Title = "t", Body = "" };', 1],
            'a blank for a nullable slot' => ['public static Note Make() => new Note { Title = "t", Footer = "" };', 0],
            'a real value' => ['public static Card Make(string title, string body) => new Card(title, body);', 0],
            'a record\'s own Null Object' => ['public sealed record Blank(string Title) { public static Blank None { get; } = new(""); }', 0],
            'a type that is not a record' => ['public static Label Make() => new Label("");', 0],
        ];
    }

    #[DataProvider('members')]
    public function test_flags_a_required_text_slot_filled_with_a_blank(string $member, int $flagged): void
    {
        $source = "public sealed record Card(string Title, string Body);\npublic sealed record Note { public required string Title { get; init; } public string Body { get; init; } = \"x\"; public string? Footer { get; init; } }\npublic sealed class Label(string text) { public string Text => text; }\npublic static class Cards\n{\n    {$member}\n}\n";
        $this->assertCount($flagged, new PlaceholderFilledDataDetector()->find(Codebase::fromString($source, 'Cards.cs')), $source);
    }
}
