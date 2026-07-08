<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Bridge;

use JesseGall\CodeCommandments\Bridge\Contracts;
use JesseGall\CodeCommandments\Bridge\TypeContract;
use PHPUnit\Framework\TestCase;

final class TypeContractTest extends TestCase
{
    public function test_an_identical_type_mirrors_the_contract(): void
    {
        $contract = new TypeContract('OrderData', ['id', 'total', 'placedAt']);

        $this->assertTrue($contract->mirroredBy('OrderData', ['id', 'total', 'placedAt']));
    }

    public function test_matching_is_spelling_insensitive_on_name_and_fields(): void
    {
        // Backend camelCase properties vs a hand-written snake_case TS type.
        $contract = new TypeContract('CustomerData', ['firstName', 'lastName', 'emailAddress']);

        $this->assertTrue($contract->mirroredBy('customer_data', ['first_name', 'last_name', 'email_address']));
    }

    public function test_a_type_that_dropped_a_field_still_mirrors_at_the_floor(): void
    {
        // The hand-written twin omits one field the Data class has: 4 shared of 5
        // combined = 0.8, exactly the floor.
        $contract = new TypeContract('ProductData', ['id', 'name', 'price', 'sku', 'stock']);

        $this->assertTrue($contract->mirroredBy('ProductData', ['id', 'name', 'price', 'sku']));
    }

    public function test_a_type_that_replaced_a_field_falls_below_the_floor(): void
    {
        // A substitution makes BOTH sides carry a unique field: 3 shared of 5
        // combined = 0.6 — the conservative choice, a renamed field breaks the mirror.
        $contract = new TypeContract('ProductData', ['id', 'name', 'price', 'sku']);

        $this->assertFalse($contract->mirroredBy('ProductData', ['id', 'name', 'price', 'stock']));
    }

    public function test_an_omitted_optional_field_does_not_dilute_the_match(): void
    {
        // Two `T|Optional` slots the server omits from the wire; the hand-written mirror leaves them out.
        // Without the Optional exemption this is 3 shared / 5 combined = 0.6 (a MISS); with it the two
        // absent-optionals drop from the union → 3/3 = 1.0, so the mirror is still caught.
        $contract = new TypeContract(
            'OrderData',
            ['id', 'total', 'placedAt', 'chrome', 'badge'],
            optionalFields: ['chrome', 'badge'],
        );

        $this->assertTrue($contract->mirroredBy('OrderData', ['id', 'total', 'placedAt']));
    }

    public function test_the_optional_exemption_does_not_rescue_a_dropped_required_field(): void
    {
        // Only `chrome` is optional; the mirror drops the required `placedAt` too and adds an unrelated
        // field — 2 shared / 4 union (chrome excluded) = 0.5, still below the floor. The exemption forgives
        // the optional's absence, not a required field's.
        $contract = new TypeContract(
            'OrderData',
            ['id', 'total', 'placedAt', 'chrome'],
            optionalFields: ['chrome'],
        );

        $this->assertFalse($contract->mirroredBy('OrderData', ['id', 'total', 'skin']));
    }

    public function test_a_shape_that_shares_only_a_few_fields_is_not_a_mirror(): void
    {
        $contract = new TypeContract('InvoiceData', ['id', 'number', 'issuedAt', 'dueAt', 'total']);

        $this->assertFalse($contract->mirroredBy('InvoiceData', ['id', 'label']));
    }

    public function test_a_matching_shape_under_a_different_name_is_not_a_mirror(): void
    {
        $contract = new TypeContract('OrderData', ['id', 'total', 'placedAt']);

        $this->assertFalse($contract->mirroredBy('CartData', ['id', 'total', 'placedAt']));
    }

    public function test_an_empty_field_set_never_mirrors(): void
    {
        $contract = new TypeContract('EmptyData', []);

        $this->assertFalse($contract->mirroredBy('EmptyData', []));
    }

    public function test_the_bag_returns_only_contracts_of_the_requested_kind(): void
    {
        $contracts = (new Contracts())->with(
            new TypeContract('OrderData', ['id']),
            new TypeContract('CustomerData', ['name']),
        );

        $this->assertCount(2, $contracts->ofType(TypeContract::class));
    }
}
