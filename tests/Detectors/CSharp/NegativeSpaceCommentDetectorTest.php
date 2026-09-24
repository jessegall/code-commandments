<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\CSharp;

use JesseGall\CodeCommandments\Cs\Codebase;
use JesseGall\CodeCommandments\Detectors\CSharp\NegativeSpaceCommentDetector;
use JesseGall\CodeCommandments\Tests\Cs\NeedsTheBridge;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * A comment defending the code against a reading nobody made — that it is not random, not a typo — says
 * what the code is not; a comment saying what it is, or what it rules out at runtime, is fine.
 */
final class NegativeSpaceCommentDetectorTest extends TestCase
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
            'a number that is not magic' => ['// 86400: not magic, just a day in seconds', 1],
            'an order kept on purpose' => ['/* deliberately not sorted */', 1],
            'a doc comment defending itself' => ['/// <summary>The rate. Not a typo: it really is 0.21.</summary>', 1],
            'what the code is' => ['// a day, in seconds', 0],
            'a rule the code enforces' => ['/// <summary>Never negative; a refund is its own line.</summary>', 0],
        ];
    }

    #[DataProvider('comments')]
    public function test_flags_a_comment_that_defends_against_a_strawman(string $comment, int $flagged): void
    {
        $source = "public sealed class Invoice\n{\n    {$comment}\n    public int Cents { get; init; }\n}\n";
        $this->assertCount($flagged, new NegativeSpaceCommentDetector()->find(Codebase::fromString($source, 'Invoice.cs')), $source);
    }
}
