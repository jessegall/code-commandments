<?php

namespace Shop\Snapshots;

/**
 * The snapshot protocol a stored aggregate answers: the state travels as an array because the store writes it
 * to one JSON column, so the shape of the parameter belongs to this class and not to the classes that answer it.
 */
abstract class StockSnapshot
{
    /**
     * @param array<string, mixed> $state
     */
    abstract public function useState(array $state): void;

    /**
     * @return array<string, mixed>
     */
    abstract public function getState(): array;
}
