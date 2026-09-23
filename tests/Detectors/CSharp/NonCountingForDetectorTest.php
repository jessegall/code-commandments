<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\CSharp;

use JesseGall\CodeCommandments\Cs\Codebase;
use JesseGall\CodeCommandments\Detectors\CSharp\NonCountingForDetector;
use JesseGall\CodeCommandments\Tests\Cs\NeedsTheBridge;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * A `for` whose step assigns the next item is a walk dressed as a count; a step that moves a counter, and a
 * `for` with no step at all, are left alone.
 */
final class NonCountingForDetectorTest extends TestCase
{
    use NeedsTheBridge;

    protected function setUp(): void
    {
        $this->requireTheBridge();
    }

    /**
     * @return array<string, array{string, int}>
     */
    public static function loops(): array
    {
        return [
            'a walk down a linked list' => ['for (var link = head; link != null; link = link.Next) { total++; }', 1],
            'a step that calls for the next item' => ['for (var link = head; link != null; link = Follow(link)) { total++; }', 1],
            'a walk with no condition' => ['for (var link = head; ; link = link.Next) { if (link == null) break; }', 1],
            'a counter going up' => ['for (var i = 0; i < 10; i++) { total++; }', 0],
            'a counter going down by two' => ['for (var i = 10; i > 0; i -= 2) { total++; }', 0],
            'two steps, one of them a counter' => ['for (var link = head; link != null; link = link.Next, total++) { }', 0],
            'a step that searches for the next place' => ['for (var at = "abc".IndexOf("b"); at >= 0; at = "abc".IndexOf("b", at + 1)) { total++; }', 1],
            'a counter moved inside an assignment' => ['var seen = new int[3]; for (var i = 0; i < 3; seen[i] = i++) { }', 0],
            'a step by a fixed amount' => ['for (var day = System.DateTime.Today; day < System.DateTime.Today.AddDays(7); day = day.AddDays(1)) { total++; }', 0],
            'no step at all' => ['for (;;) { break; }', 0],
        ];
    }

    #[DataProvider('loops')]
    public function test_flags_a_for_whose_step_counts_nothing(string $loop, int $flagged): void
    {
        $source = "public class Link { public Link? Next; }\npublic class Chain\n{\n    private static Link? Follow(Link link) => link.Next;\n\n    public static int Length(Link? head)\n    {\n        var total = 0;\n        {$loop}\n        return total;\n    }\n}\n";
        $this->assertCount($flagged, new NonCountingForDetector()->find(Codebase::fromString($source, 'Chain.cs')), $source);
    }
}
