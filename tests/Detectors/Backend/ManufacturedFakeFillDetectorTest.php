<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\Backend;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Detectors\Backend\ManufacturedFakeFillDetector;
use PHPUnit\Framework\TestCase;

final class ManufacturedFakeFillDetectorTest extends TestCase
{
    public function test_flags_empty_literal_fills_of_arguments_only(): void
    {
        $code = <<<'PHP'
        <?php
        class Importer
        {
            public function fake(array $row): Product
            {
                return new Product(
                    name: $row['name'] ?? '',
                    price: (int) ($row['price'] ?? 0),
                    currency: $row['currency'] ?? 'EUR',
                );
            }

            public function getter(array $row): string
            {
                return $row['name'] ?? '';
            }
        }
        PHP;

        $hits = (new ManufacturedFakeFillDetector)->find(Codebase::fromString($code));
        $lines = array_map(static fn ($m): int => $m->line(), $hits);
        sort($lines);

        // name: ?? '' and price: ?? 0 (through the cast) — not currency: ?? 'EUR'
        // (a real default), not the getter return (no argument fill).
        $this->assertSame([7, 8], $lines);
    }

    public function test_does_not_flag_an_empty_array_fill(): void
    {
        // `?? []` is the collection's own identity ("no items" — e.g. the empty base of a
        // recursive tree merge), a real domain answer, not a manufactured scalar fake (#398).
        $code = <<<'PHP'
        <?php
        class TreeDiff
        {
            public static function apply(array $base, array $patch): array
            {
                return array_map(
                    static fn (array $child): array => self::apply(self::childByKey($base, 'k') ?? [], $child),
                    $patch,
                );
            }

            private static function childByKey(array $node, string $key): ?array
            {
                return null;
            }
        }
        PHP;

        $this->assertSame([], (new ManufacturedFakeFillDetector)->find(Codebase::fromString($code)));
    }
}
