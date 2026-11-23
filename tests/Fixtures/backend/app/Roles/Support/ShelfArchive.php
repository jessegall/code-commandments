<?php

namespace Shop\Roles\Support;

/**
 * Moves a shelf's stock into the archive: a concrete service of the Shop\Roles package, which autoloads it.
 */
final class ShelfArchive
{
    /**
     * @var list<string>
     */
    private array $archived = [];

    public function archive(string $shelf): void
    {
        $this->archived[] = $shelf;
    }

    public function holds(string $shelf): bool
    {
        return in_array($shelf, $this->archived, true);
    }
}
