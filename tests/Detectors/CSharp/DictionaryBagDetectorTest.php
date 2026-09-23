<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\CSharp;

use JesseGall\CodeCommandments\Cs\Codebase;
use JesseGall\CodeCommandments\Detectors\CSharp\DictionaryBagDetector;
use JesseGall\CodeCommandments\Tests\Cs\NeedsTheBridge;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * A string-keyed dictionary or JSON object read by a key written in the source is a record nobody
 * declared — read directly, or through a helper handed the key. A dictionary keyed by data, a read by a
 * computed key, and the factory that builds a record from the input are not.
 */
final class DictionaryBagDetectorTest extends TestCase
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
            'an indexer with a string literal' => ['public int Qty(Dictionary<string, object> row) => (int) row["qty"];', 1],
            'TryGetValue with a const key' => ['private const string Key = "sku"; public string Sku(IReadOnlyDictionary<string, string> row) => row.TryGetValue(Key, out var sku) ? sku : "none";', 1],
            'a JSON element read by property' => ['public string Name(JsonElement json) => json.GetProperty("name").GetString() ?? "none";', 1],
            'a helper handed the key' => ['private static string Cell(IReadOnlyDictionary<string, string> row, string key) => row[key]; public string City(IReadOnlyDictionary<string, string> row) => Cell(row, "city");', 1],
            'a dictionary keyed by data' => ['public int Stock(Dictionary<string, int> stock, string sku) => stock[sku];', 0],
            'a dictionary keyed by numbers' => ['public string Label(Dictionary<int, string> labels) => labels[3];', 0],
            'a parser building a record with new' => ['public sealed record Config(string Host, string Port); public Config Parse(IReadOnlyDictionary<string, string> values) => new Config(values["host"], values["port"]);', 0],
            'a factory building the record' => ['public sealed record Line(string Sku, int Qty) { public static Line From(IReadOnlyDictionary<string, string> row) => new(row["sku"], int.Parse(row["qty"])); }', 0],
        ];
    }

    #[DataProvider('members')]
    public function test_flags_a_dictionary_read_as_a_record(string $member, int $flagged): void
    {
        $source = "using System.Collections.Generic;\nusing System.Text.Json;\n\npublic class Rows\n{\n    {$member}\n}\n";

        $this->assertCount($flagged, new DictionaryBagDetector()->find(Codebase::fromString($source, 'Rows.cs')), $source);
    }
}
