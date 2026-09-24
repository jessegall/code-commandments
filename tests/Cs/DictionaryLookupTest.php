<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Cs;

use JesseGall\CodeCommandments\Cs\Codebase;
use JesseGall\CodeCommandments\Cs\NodeMatch;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * A read of a dictionary by key is a lookup however it is spelled — an indexer, `TryGetValue`, or the
 * `GetValueOrDefault` extension, whose own type is the static class declaring it rather than the map.
 */
final class DictionaryLookupTest extends TestCase
{
    use NeedsTheBridge;

    protected function setUp(): void
    {
        $this->requireTheBridge();
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function reads(): array
    {
        return [
            'an indexer' => ['row["sku"]', 'ElementAccessExpression'],
            'TryGetValue' => ['row.TryGetValue("sku", out var found)', 'InvocationExpression'],
            'the GetValueOrDefault extension' => ['row.GetValueOrDefault("sku")', 'InvocationExpression'],
        ];
    }

    #[DataProvider('reads')]
    public function test_reads_a_dictionary_by_key_as_a_lookup(string $read, string $kind): void
    {
        $source = "using System.Collections.Generic;\npublic static class Rows\n{\n    public static object? Sku(Dictionary<string, string> row) => {$read};\n}\n";
        $found = array_values(array_filter(Codebase::fromString($source, 'Rows.cs')->whereExpression(static fn ($node): bool => $node->kind === $kind)->get(), static fn (NodeMatch $match): bool => $match->node->isLookup()));

        $this->assertCount(1, $found, $read);
        $this->assertTrue($found[0]->node->isKeyedRead(), $read);
    }
}
