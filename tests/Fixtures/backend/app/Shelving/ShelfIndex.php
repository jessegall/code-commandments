<?php

namespace Shop\Shelving;

/**
 * A keyed shelf register. `reserve()` asks for the slug, so every caller slugs first.
 */
final class ShelfIndex
{
    /**
     * @var array<string, int>
     */
    private array $taken = [];

    public function reserve(string $slug, int $bay): void
    {
        $this->taken[$slug] = $bay;
    }

    public function bayOf(string $slug): int
    {
        return $this->taken[$slug] ?? 0;
    }
}
