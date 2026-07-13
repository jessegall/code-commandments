<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\Backend;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Detectors\Backend\ArrayBagDetector;
use PHPUnit\Framework\TestCase;

final class ArrayBagDetectorTest extends TestCase
{
    public function test_flags_string_indexing_of_an_array_parameter_only(): void
    {
        $code = <<<'PHP'
        <?php
        class S
        {
            public function render(array $b): string
            {
                return (string) $b['total'];
            }

            public function lookup(array $m, string $k): mixed
            {
                return $m[$k] ?? null;
            }

            public function first(array $cols): string
            {
                return (string) ($cols[0] ?? '');
            }

            public function local(): string
            {
                $x = ['total' => 1];

                return (string) $x['total'];
            }
        }
        PHP;

        $hits = (new ArrayBagDetector)->find(Codebase::fromString($code));

        // only render() string-indexes an ARRAY PARAMETER by a literal key
        $this->assertSame(['S::render'], array_map(static fn ($m): string => $m->scope(), $hits));
    }

    public function test_does_not_flag_the_serialization_protocol_boundary(): void
    {
        // Reported (#340): PHP hands __unserialize the raw property bag — its signature is
        // dictated by the language, so string-indexing it (and the private helper it hands
        // the bag to) IS the canonical deserialization parse point, not an array-bag sin.
        // A plain method in the same class string-indexing a parameter is still the sin.
        $code = <<<'PHP'
        <?php
        class SaleItem
        {
            private string $variantId = '';
            private array $discounts = [];

            public function __unserialize(array $data): void
            {
                $this->variantId = $data['variantId'];
                $this->discounts = $this->migrateDiscounts($data);
            }

            private function migrateDiscounts(array $data): array
            {
                return $data['discounts'] ?? [];
            }

            public function apply(array $bag): string
            {
                return (string) $bag['code'];
            }
        }
        PHP;

        $hits = (new ArrayBagDetector)->find(Codebase::fromString($code));

        $this->assertSame(['SaleItem::apply'], array_map(static fn ($m): string => $m->scope(), $hits));
    }

    public function test_still_flags_a_helper_also_called_outside_the_boundary(): void
    {
        // The bag helper is only exempt while EVERY caller is the serialization hook — a
        // second call site from ordinary code means the array bag rides the public API.
        $code = <<<'PHP'
        <?php
        class Order
        {
            private array $lines = [];

            public function __unserialize(array $data): void
            {
                $this->lines = $this->readLines($data);
            }

            public function refresh(array $payload): void
            {
                $this->lines = $this->readLines($payload);
            }

            private function readLines(array $data): array
            {
                return $data['lines'] ?? [];
            }
        }
        PHP;

        $hits = (new ArrayBagDetector)->find(Codebase::fromString($code));

        $this->assertSame(['Order::readLines'], array_map(static fn ($m): string => $m->scope(), $hits));
    }
}
