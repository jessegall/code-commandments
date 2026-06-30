<?php

namespace Shop\Shipping;

use JesseGall\CodeCommandments\Sins\Backend\NearDuplicateFunction;

use JesseGall\CodeCommandments\Testing\Sinful;

final class RouteCostEstimator
{
    /** @var list<int> */
    private array $entries = [];

    private string $carrier = 'default';

    public function leg(int $distanceKm): void
    {
        $this->entries[] = $distanceKm;
    }

    public function via(string $carrier): self
    {
        $clone = clone $this;
        $clone->carrier = $carrier;

        return $clone;
    }

    public function describe(): string
    {
        $hops = count($this->entries);
        $furthest = $this->entries === [] ? 0 : max($this->entries);

        return $this->carrier . ': ' . $hops . ' legs, furthest ' . $furthest . 'km';
    }

    public function topLegs(): string
    {
        $sorted = $this->entries;
        rsort($sorted);
        $top = array_slice($sorted, 0, 3);

        return implode(' > ', array_map(static fn (int $km): string => $km . 'km', $top));
    }

    #[Sinful(NearDuplicateFunction::class)]
    public function estimateFrom(int $surcharge): int
    {
        $cost = $surcharge;

        foreach ($this->entries as $leg) {
            if ($leg > 0) {
                $cost += $leg * 3;
            }
        }

        return $cost;
    }
}
