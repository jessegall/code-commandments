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

    /**
     * @return list<ExprMatch>
     */
    private function findIn(string $source): array
    {
        return new DictBagDetector()->find(Codebase::fromString($source));
    }
}
