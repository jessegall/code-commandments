<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Py;

use JesseGall\CodeCommandments\Py\Codebase;
use JesseGall\CodeCommandments\Py\Node\ClassDef;
use JesseGall\CodeCommandments\Py\NodeMatch;
use PHPUnit\Framework\TestCase;

final class AttributeFlowTest extends TestCase
{
    private const string SHOP = <<<'PY'
        class Address:
            def __init__(self, street, city):
                self.street = street
                self.city = city


        class Customer:
            def __init__(self, street: str, city: str, email: str | None, note: str | None) -> None:
                self.street = street
                self.city = city
                self.email = email
                self.note = note

            def address(self) -> Address:
                return Address(self.street, self.city)

            def label(self) -> tuple:
                return (self.city, self.street)

            def greet(self) -> str:
                return "hi " + self.email.lower()

            def mail(self) -> None:
                send(self.email.strip(), self.note)

            def memo(self) -> str:
                if self.note is None:
                    return ""
                return self.note.upper()

            def shout(self) -> str:
                return format(self.street, self.city)
        PY;

    public function test_attributes_assembled_into_one_value_together_group(): void
    {
        $codebase = $this->codebase();

        $this->assertSame([['city', 'street'], ['city', 'street']], $this->customer($codebase)->selfAttributeGroupsAssembled($codebase));
    }

    public function test_attributes_tested_for_absence(): void
    {
        $customer = $this->customer($this->codebase())->node;
        $this->assertInstanceOf(ClassDef::class, $customer);

        $this->assertSame(['note'], $customer->attributesTestedForAbsence());
    }

    public function test_a_verdict_counts_reads_that_assume_the_attribute_and_reads_that_guard_it(): void
    {
        $codebase = $this->codebase();
        $customer = $this->customer($codebase)->node;
        $this->assertInstanceOf(ClassDef::class, $customer);
        $flow = $codebase->attributeFlow();

        $email = $flow->verdict($customer, 'email');
        $note = $flow->verdict($customer, 'note');

        $this->assertSame([2, 0], [$email->assume, $email->guard]);
        $this->assertSame([1, 1], [$note->assume, $note->guard]);
    }

    private function codebase(): Codebase
    {
        return Codebase::fromString(self::SHOP, 'shop.py');
    }

    private function customer(Codebase $codebase): NodeMatch
    {
        return array_values(array_filter($codebase->whereClass()->get(), static fn (NodeMatch $match): bool => $match->name() === 'Customer'))[0];
    }
}
