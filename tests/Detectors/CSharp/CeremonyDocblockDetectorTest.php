<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\CSharp;

use JesseGall\CodeCommandments\Cs\Codebase;
use JesseGall\CodeCommandments\Detectors\CSharp\CeremonyDocblockDetector;
use JesseGall\CodeCommandments\Tests\Cs\NeedsTheBridge;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * A doc comment whose every tag is empty or only repeats the member's own names and types says nothing the
 * signature does not; one sentence of its own, or a tag about something else, is documentation.
 */
final class CeremonyDocblockDetectorTest extends TestCase
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
            'an empty stub' => ["public sealed class Orders\n{\n    /// <summary>\n    /// \n    /// </summary>\n    /// <param name=\"order\"></param>\n    /// <returns></returns>\n    public int Total(Order order) => 0;\n}\npublic sealed class Order { }", 1],
            'tags that repeat the names' => ["public sealed class Orders\n{\n    /// <summary>Gets the order.</summary>\n    /// <param name=\"orderId\">The order id.</param>\n    /// <returns>The <see cref=\"Order\"/>.</returns>\n    public Order GetOrder(int orderId) => new();\n}\npublic sealed class Order { }", 1],
            'a record whose parameters are restated' => ["/// <param name=\"Status\">The status.</param>\n/// <param name=\"Reason\">The reason.</param>\npublic sealed record Attempt(int Status, string Reason);", 1],
            'a summary of its own' => ["public sealed class Orders\n{\n    /// <summary>Totals the lines that were not cancelled.</summary>\n    /// <param name=\"order\">The order.</param>\n    public int Total(Order order) => 0;\n}\npublic sealed class Order { }", 0],
            'a parameter described' => ["public sealed class Orders\n{\n    /// <param name=\"order\">An order that has been placed.</param>\n    public int Total(Order order) => 0;\n}\npublic sealed class Order { }", 0],
            'an exception it throws' => ["public sealed class Orders\n{\n    /// <param name=\"order\">The order.</param>\n    /// <exception cref=\"System.ArgumentException\"></exception>\n    public int Total(Order order) => 0;\n}\npublic sealed class Order { }", 0],
            'a summary alone' => ["public sealed class Orders\n{\n    /// <summary>Gets the order.</summary>\n    public Order GetOrder() => new();\n}\npublic sealed class Order { }", 0],
            'inherited documentation' => ["public sealed class Orders : System.IDisposable\n{\n    /// <inheritdoc/>\n    public void Dispose() { }\n}", 0],
        ];
    }

    #[DataProvider('sources')]
    public function test_flags_a_doc_comment_that_only_restates_the_signature(string $source, int $flagged): void
    {
        $this->assertCount($flagged, new CeremonyDocblockDetector()->find(Codebase::fromString($source, 'Orders.cs')), $source);
    }
}
