<?php

namespace Shop\Reaches;

use JesseGall\CodeCommandments\Testing\Righteous;

final class PickList
{
    public function __construct(private PickLines $lines) {}

    #[Righteous(RepeatedReach::class)]
    public function withLine(string $sku): self
    {
        $copy = clone $this;
        $count = $copy->lines->count();
        $first = $copy->lines->first();

        return $count > 0 && $first !== $sku ? $copy : $this;
    }
}
