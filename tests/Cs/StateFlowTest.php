<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Cs;

use JesseGall\CodeCommandments\Cs\Codebase;
use JesseGall\CodeCommandments\Cs\Node;
use JesseGall\CodeCommandments\Cs\StateFlow;
use PHPUnit\Framework\TestCase;

/**
 * Which of a type's own fields each member reads, and where a nullable field is assigned and read — the two
 * readings the coupled-fields, phantom-nullable and feature-envy rules are built on.
 */
final class StateFlowTest extends TestCase
{
    use NeedsTheBridge;

    private const string SOURCE = <<<'CS'
        public sealed class Other { public int Cents; }

        public sealed class Invoice
        {
            private int cents;
            private string currency = "EUR";
            private Batch? batch;

            public Invoice(int cents) { this.cents = cents; }

            public string Label() => $"{cents} {this.currency}";

            public int Borrowed(Other other) => other.Cents + cents;

            public void Open(Batch b) { batch = b; }

            public void Close() { batch = null; }

            public bool Permits() => batch?.Allows() ?? false;

            public int Shadowed() { var cents = 1; return cents; }
        }

        public sealed class Batch { public bool Allows() => true; }
        CS;

    protected function setUp(): void
    {
        $this->requireTheBridge();
    }

    public function test_reads_which_fields_each_member_reads_together(): void
    {
        $reads = $this->flow()->readsByMember();

        $this->assertSame(['cents', 'currency'], $reads['Label']);
        $this->assertSame(['cents'], $reads['Borrowed']);
        $this->assertSame(['batch'], $reads['Permits']);
        $this->assertArrayNotHasKey('Shadowed', $reads);
        $this->assertArrayNotHasKey('Open', $reads);
    }

    public function test_knows_the_nullable_fields(): void
    {
        $this->assertSame(['batch'], $this->flow()->nullableFields());
    }

    public function test_finds_where_a_nullable_field_is_assigned_and_read(): void
    {
        $flow = $this->flow();

        $this->assertSame(['b', 'null'], array_map(static fn (Node $value): string => $value->is('NullLiteralExpression') ? 'null' : (string) $value->name, $flow->assignedValues('batch')));
        $this->assertCount(1, $flow->reads('batch'));
        $this->assertCount(1, $flow->assignedValues('cents'));
    }

    private function flow(): StateFlow
    {
        $invoice = array_values(array_filter(Codebase::fromString(self::SOURCE, 'Invoice.cs')->whereType()->get(), static fn ($type): bool => $type->node->name === 'Invoice'))[0];

        return new StateFlow($invoice->node);
    }
}
