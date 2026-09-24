<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\CSharp;

use JesseGall\CodeCommandments\Cs\Codebase;
use JesseGall\CodeCommandments\Detectors\CSharp\ArchaeologyCommentDetector;
use JesseGall\CodeCommandments\Tests\Cs\NeedsTheBridge;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * A comment that tells the code's past — what it used to be, where it came from, what was changed — describes
 * a version nobody is reading; a comment about the code as it is, and a doc comment's markup, are fine.
 */
final class ArchaeologyCommentDetectorTest extends TestCase
{
    use NeedsTheBridge;

    protected function setUp(): void
    {
        $this->requireTheBridge();
    }

    /**
     * @return array<string, array{string, int}>
     */
    public static function comments(): array
    {
        return [
            'a line comment' => ['// formerly lived in CheckoutService', 1],
            'a block comment' => ['/* refactored to use the cache */', 1],
            'a doc comment' => ['/// <summary>No longer a dictionary; used to be keyed by id.</summary>', 1],
            'the present' => ['// the total in cents', 0],
            'a present doc comment' => ['/// <summary>The total, in cents.</summary>', 0],
        ];
    }

    #[DataProvider('comments')]
    public function test_flags_a_comment_that_tells_the_codes_past(string $comment, int $flagged): void
    {
        $source = "public sealed class Invoice\n{\n    {$comment}\n    public int Cents { get; init; }\n}\n";
        $this->assertCount($flagged, new ArchaeologyCommentDetector()->find(Codebase::fromString($source, 'Invoice.cs')), $source);
    }
}
