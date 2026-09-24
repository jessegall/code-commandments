<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\CSharp;

use JesseGall\CodeCommandments\Cs\Codebase;
use JesseGall\CodeCommandments\Detectors\CSharp\ArrayReturnBagDetector;
use JesseGall\CodeCommandments\Tests\Cs\NeedsTheBridge;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * A member that hands back a dictionary built with two or more fixed string keys returns a record nobody
 * declared; a dictionary with one key, one built from data, or one kept rather than returned is fine.
 */
final class ArrayReturnBagDetectorTest extends TestCase
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
            'an expression body with indexer entries' => ['public Dictionary<string, object> Summary(int n) => new() { ["sku"] = n, ["qty"] = 2 };', 1],
            'a return with pair entries' => ['public IDictionary<string, int> Counts(int n) { return new Dictionary<string, int> { { "sku", n }, { "qty", 2 } }; }', 1],
            'a return in parentheses' => ['public Dictionary<string, string> Names() => (new Dictionary<string, string> { ["first"] = "a", ["last"] = "b" });', 1],
            'a single key' => ['public Dictionary<string, int> One(int n) => new() { ["sku"] = n };', 0],
            'keys from data' => ['public Dictionary<string, int> Of(string key, int n) => new() { [key] = n, ["qty"] = 2 };', 0],
            'an interface member returning its contract\'s shape' => ['public interface IDetailed { IReadOnlyDictionary<string, object> Details { get; } } public sealed class Refused : IDetailed { public IReadOnlyDictionary<string, object> Details => new Dictionary<string, object> { ["sku"] = 1, ["qty"] = 2 }; }', 0],
            'kept in a field, not returned' => ['private readonly Dictionary<string, int> limits = new() { ["small"] = 1, ["large"] = 9 };', 0],
        ];
    }

    #[DataProvider('members')]
    public function test_flags_a_returned_dictionary_of_fixed_keys(string $member, int $flagged): void
    {
        $source = "using System.Collections.Generic;\npublic class Report\n{\n    {$member}\n}\n";
        $this->assertCount($flagged, new ArrayReturnBagDetector()->find(Codebase::fromString($source, 'Report.cs')), $source);
    }
}
