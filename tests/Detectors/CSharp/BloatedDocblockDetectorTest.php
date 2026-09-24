<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\CSharp;

use JesseGall\CodeCommandments\Cs\Codebase;
use JesseGall\CodeCommandments\Detectors\CSharp\BloatedDocblockDetector;
use JesseGall\CodeCommandments\Tests\Cs\NeedsTheBridge;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * A type whose doc comment runs to two or more paragraphs is usually a type that does too much; one paragraph,
 * and a long comment on a method, are fine.
 */
final class BloatedDocblockDetectorTest extends TestCase
{
    use NeedsTheBridge;

    protected function setUp(): void
    {
        $this->requireTheBridge();
    }

    /**
     * @return array<string, array{string, int}>
     */
    public static function sources(): array
    {
        return [
            'a blank line between paragraphs' => ["/// <summary>\n/// Holds an order's lines.\n///\n/// Also prices them and ships them.\n/// </summary>\npublic sealed class Order { }", 1],
            'a summary and remarks' => ["/// <summary>Holds an order's lines.</summary>\n/// <remarks>\n/// Also prices them and ships them.\n/// </remarks>\npublic sealed class Order { }", 1],
            'one paragraph' => ["/// <summary>\n/// Holds an order's lines,\n/// in the order they were added.\n/// </summary>\npublic sealed class Order { }", 0],
            'a summary and parameter tags' => ["/// <summary>\n/// What an attempt produced.\n/// </summary>\n/// <param name=\"Status\">The outcome.</param>\n/// <param name=\"Reason\">Why it was blocked.</param>\npublic sealed record Attempt(int Status, string Reason);", 0],
            'paragraphs split by para' => ["/// <summary>\n/// <para>Holds an order's lines.</para>\n/// <para>Also prices them.</para>\n/// </summary>\npublic sealed class Order { }", 1],
            'a long comment on a method' => ["public sealed class Order\n{\n    /// <summary>Totals the lines.</summary>\n    /// <remarks>\n    /// Skips cancelled ones.\n    /// </remarks>\n    public int Total() => 0;\n}", 0],
        ];
    }

    #[DataProvider('sources')]
    public function test_flags_a_type_whose_doc_comment_runs_to_paragraphs(string $source, int $flagged): void
    {
        $this->assertCount($flagged, new BloatedDocblockDetector()->find(Codebase::fromString($source, 'Order.cs')), $source);
    }
}
