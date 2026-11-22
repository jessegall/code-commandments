<?php

namespace Spatie\EventSourcing\AggregateRoots;

abstract class AggregateRoot
{
    public static function retrieve(string $uuid): static {}
    public function persist(): static {}
}
