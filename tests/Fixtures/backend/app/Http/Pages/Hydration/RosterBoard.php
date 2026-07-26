<?php

namespace Shop\Http\Pages\Hydration;

use JesseGall\CodeCommandments\Sins\Backend\Spatie\NestedTypeMissingTypeScript;
use JesseGall\CodeCommandments\Testing\Sinful;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/*
 * A COLLECTION-shaped scenario, unlike the nullable-object gauges — a roster board whose seats are a
 * `#[DataCollectionOf]` list of a nested `Seat` Data that lacks `#[TypeScript]`. The transformer emits the
 * element as `undefined`, so the whole `seats` array is malformed on the frontend.
 */
#[Sinful(NestedTypeMissingTypeScript::class)]
#[TypeScript]
final class RosterBoard extends Data
{
    /**
     * @var list<Seat>
     */
    #[DataCollectionOf(Seat::class)]
    public readonly array $seats;

    public function __construct(
        public readonly string $title,
        array $seats = [],
    ) {
        $this->seats = $seats;
    }

    public function occupied(): int
    {
        return count(array_filter($this->seats, static fn (Seat $seat): bool => $seat->taken));
    }

    public function isFull(): bool
    {
        return $this->seats !== [] && $this->occupied() === count($this->seats);
    }
}

final class Seat extends Data
{
    public function __construct(
        public readonly string $row,
        public readonly int $number,
        public readonly bool $taken = false,
    ) {}
}
