<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\CSharp;

use JesseGall\CodeCommandments\Cs\Codebase;
use JesseGall\CodeCommandments\Detectors\CSharp\RestatedCommentDetector;
use JesseGall\CodeCommandments\Tests\Cs\NeedsTheBridge;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * A comment above a statement whose every word the statement already spells narrates it; a comment giving a
 * reason, a one-word label, and commented-out code are something else.
 */
final class RestatedCommentDetectorTest extends TestCase
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
            'narrating an assignment' => ["// set the total to the order total\nvar total = order.Total;\nreturn total;", 1],
            'narrating a loop' => ["// loop over each line\nforeach (var line in order.Lines)\n{\n    line.Ship();\n}\nreturn 0;", 1],
            'narrating a return, over two lines' => ["// return the\n// order total\nreturn order.Total;", 1],
            'a reason the code cannot state' => ["// the warehouse rounds down, so this must too\nvar total = order.Total;\nreturn total;", 0],
            'a one-word label' => ["// total\nreturn order.Total;", 0],
            'commented-out code' => ["// return order.Total * 2;\nreturn order.Total;", 0],
            'a comment trailing code' => ["var total = order.Total; // the order total\nreturn total;", 0],
        ];
    }

    #[DataProvider('bodies')]
    public function test_flags_a_comment_that_narrates_the_statement_below_it(string $body, int $flagged): void
    {
        $indented = implode("\n", array_map(static fn (string $line): string => "        {$line}", explode("\n", $body)));
        $source = "public sealed class Order\n{\n    public int Total { get; init; }\n    public List<Line> Lines { get; } = [];\n}\npublic sealed class Line\n{\n    public void Ship() { }\n}\npublic sealed class Pricer\n{\n    public int Price(Order order)\n    {\n{$indented}\n    }\n}\n";

        $this->assertCount($flagged, new RestatedCommentDetector()->find(Codebase::fromString($source, 'Pricer.cs')), $source);
    }
}
