<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\Backend;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Detectors\Backend\ArrayReturnBagDetector;
use PHPUnit\Framework\TestCase;

final class ArrayReturnBagDetectorTest extends TestCase
{
    public function test_flags_a_returned_multi_field_string_keyed_array_only(): void
    {
        $code = <<<'PHP'
        <?php
        class S
        {
            public function bag(): array
            {
                return ['subtotal' => 1, 'tax' => 2, 'total' => 3];
            }

            public function wrapped(): array
            {
                return ['ok' => true];
            }

            public function items(): array
            {
                return [1, 2, 3];
            }

            public function passthrough(array $x): array
            {
                return $x;
            }
        }
        PHP;

        $hits = (new ArrayReturnBagDetector)->find(Codebase::fromString($code));

        $this->assertSame(['S::bag'], array_map(static fn ($m): string => $m->scope(), $hits));
    }

    public function test_does_not_flag_a_json_schema_contract_shape(): void
    {
        // The schema skeleton uses variables for properties/required, so the literal-
        // nesting reject can't see it — the 'type' + structural-keyword vocabulary can.
        $code = <<<'PHP'
        <?php
        class Composer
        {
            public function responseFormat(array $properties, array $required): array
            {
                return [
                    'type' => 'object',
                    'properties' => $properties,
                    'required' => $required,
                    'additionalProperties' => false,
                ];
            }

            public function enumField(array $values): array
            {
                return [
                    'type' => 'string',
                    'enum' => $values,
                    'description' => 'one of the allowed values',
                ];
            }

            // a genuine domain bag in the same class is still flagged
            public function money(): array
            {
                return ['amount' => 1, 'currency' => 'EUR'];
            }
        }
        PHP;

        $hits = (new ArrayReturnBagDetector)->find(Codebase::fromString($code));

        $this->assertSame(['Composer::money'], array_map(static fn ($m): string => $m->scope(), $hits));
    }

    public function test_leaves_a_lookup_table_of_one_types_constants_alone(): void
    {
        $code = <<<'PHP'
        <?php
        class S
        {
            // a dictionary: every value is a Permission, the keys are data, and
            // array<string, Permission> is already the total type
            public function table(): array
            {
                return [
                    'dashboard.view' => Permission::SERVES_CUSTOMERS,
                    'orders.index' => Permission::SERVES_CUSTOMERS,
                    'orders.create' => Permission::RUNS_THE_SHIFT,
                ];
            }

            // values of DIFFERENT types are fields of one concept — still a bag
            public function mixed(): array
            {
                return ['method' => Permission::SERVES_CUSTOMERS, 'currency' => Currency::EUR];
            }

            // a homogeneous map of CONSTRUCTED objects can still be a record
            public function charges(): array
            {
                return ['shipping' => new Money(5), 'handling' => new Money(2)];
            }
        }
        PHP;

        $hits = (new ArrayReturnBagDetector)->find(Codebase::fromString($code));
        $scopes = array_map(static fn ($m): string => $m->scope(), $hits);
        sort($scopes);

        $this->assertSame(['S::charges', 'S::mixed'], $scopes);
    }
}
