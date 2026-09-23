<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\Python;

use JesseGall\CodeCommandments\Detectors\Python\DictBagDetector;
use JesseGall\CodeCommandments\Py\Codebase;
use JesseGall\CodeCommandments\Py\ExprMatch;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DictBagDetectorTest extends TestCase
{
    /**
     * @return iterable<string, array{string, int}>
     */
    public static function bags(): iterable
    {
        yield 'dict[str, Any] read by subscript' => ["def total(line: dict[str, Any]) -> int:\n    return line['quantity'] * line['price']\n", 2];
        yield 'a bare dict read by get' => ["def label(row: dict) -> str:\n    return row.get('name')\n", 1];
        yield 'a Mapping' => ["def ship(order: Mapping[str, object]):\n    send(order['address'])\n", 1];
        yield 'an annotated local' => ["def a(raw):\n    payload: dict = json.loads(raw)\n    return payload['sku']\n", 1];
    }

    #[DataProvider('bags')]
    public function test_flags_every_string_key_read_of_a_dict_typed_name(string $source, int $reads): void
    {
        $this->assertCount($reads, $this->findIn($source));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function notThisSin(): iterable
    {
        yield 'a key that is data' => ["def level(stock: dict[str, int], sku: str) -> int:\n    return stock[sku]\n"];
        yield 'an unannotated name' => ["def a(row):\n    return row['sku']\n"];
        yield 'a dataclass attribute' => ["def a(line: Line) -> int:\n    return line.quantity\n"];
        yield 'a named constructor' => ["class Line:\n    @classmethod\n    def from_payload(cls, payload: dict) -> 'Line':\n        return cls(payload['sku'], payload['quantity'])\n"];
    }

    #[DataProvider('notThisSin')]
    public function test_leaves_maps_typed_objects_and_the_hydration_boundary(string $source): void
    {
        $this->assertSame([], $this->findIn($source));
    }

    public function test_a_string_key_handed_to_a_helper_that_reads_by_it_is_the_same_read(): void
    {
        $helpers = "def text_of(raw: dict, *keys) -> str:\n    for key in keys:\n        value = raw.get(key)\n        if value:\n            return str(value)\n    return ''\n\n\ndef mapping_of(raw, key):\n    return raw[key]\n\n\n";
        $source = $helpers . "def label(row):\n    return text_of(row, 'title', 'name') + str(mapping_of(row, key='meta'))\n";

        $this->assertSame([14, 14], array_map(static fn (ExprMatch $match): int => $match->line(), $this->findIn($source)));
    }

    public function test_a_helper_handed_a_key_that_is_data_is_left_alone(): void
    {
        $source = "def level(stock, sku):\n    return stock[sku]\n\n\ndef report(stock, sku):\n    return level(stock, sku)\n";

        $this->assertSame([], $this->findIn($source));
    }

    /**
     * @return list<ExprMatch>
     */
    private function findIn(string $source): array
    {
        return new DictBagDetector()->find(Codebase::fromString($source));
    }
}
