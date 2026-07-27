<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Ast\Support;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Detectors\Backend\ArrayReturnBagDetector;
use PHPUnit\Framework\TestCase;

/**
 * The serialization boundary, drawn: an array whose every value is read off ONE already-typed object
 * is that object's wire shape (the type exists — a second one would only be flattened into the same
 * array), while an array gathered from several places, from an untyped source, or from another
 * object's INTERNALS is the unborn type the value-objects rule is about.
 */
final class ProjectionTest extends TestCase
{
    /**
     * @return list<int>  the line of every flagged array literal
     */
    private function flagged(string $code): array
    {
        return array_map(
            static fn ($finding): int => $finding->line(),
            new ArrayReturnBagDetector()->find(Codebase::fromString($code)),
        );
    }

    public function test_a_serializer_of_the_objects_own_fields_is_not_a_bag(): void
    {
        $code = <<<'PHP'
        <?php
        final class Leg
        {
            public function __construct(public string $from, public string $to) {}

            public function toWire(): array
            {
                return ['from' => $this->from, 'to' => $this->to];
            }
        }

        final class Shipment
        {
            public function __construct(public string $carrier, public Leg $first, public ?array $legs) {}

            public function toWire(): array
            {
                return [
                    'carrier' => $this->carrier,
                    'first' => $this->first->toWire(),
                    'legs' => $this->legs === null ? null : array_map(static fn (Leg $leg): array => $leg->toWire(), $this->legs),
                ];
            }
        }
        PHP;

        $this->assertSame([], $this->flagged($code), 'a field dressed by a call, a guard or a map is still the object own state');
    }

    public function test_a_row_projected_from_one_value_typed_parameter_is_not_a_bag(): void
    {
        $code = <<<'PHP'
        <?php
        enum Status: string { case Ok = 'ok'; }

        final class Outcome
        {
            public function __construct(public Status $status, public ?string $error) {}
        }

        final class Run
        {
            private static function rowFor(Outcome $outcome): array
            {
                return ['status' => $outcome->status->value, 'error' => $outcome->error];
            }
        }
        PHP;

        $this->assertSame([], $this->flagged($code), "a backed enum's ->value is the field's own scalar, not a reach into an object");
    }

    public function test_an_untyped_or_scattered_source_is_still_a_bag(): void
    {
        $code = <<<'PHP'
        <?php
        final class Registrar
        {
            public function __construct(public string $id) {}

            public function fromRow(array $row): array
            {
                return ['name' => $row['name'], 'email' => $row['email']];
            }

            public function fromRequest(Request $request): array
            {
                return ['name' => $request->name, 'email' => $request->email];
            }

            public function scattered(Order $order): array
            {
                return ['id' => $this->id, 'total' => $order->total];
            }
        }
        PHP;

        $this->assertSame([8, 13, 18], $this->flagged($code));
    }

    public function test_computing_the_values_or_reading_a_fields_internals_is_still_a_bag(): void
    {
        $code = <<<'PHP'
        <?php
        final class Point
        {
            public function __construct(public float $lat, public float $lng) {}
        }

        final class Facets
        {
            public function __construct(public Point $origin) {}

            public function marker(): array
            {
                return ['lat' => $this->origin->lat, 'lng' => $this->origin->lng];
            }

            public function computed(): array
            {
                return ['categories' => $this->byCategory(), 'ratings' => $this->byRating()];
            }

            private function byCategory(): array { return []; }

            private function byRating(): array { return []; }
        }
        PHP;

        $this->assertSame([13, 18], $this->flagged($code), 'reaching through a field, and computing results on $this, are both bags');
    }
}
