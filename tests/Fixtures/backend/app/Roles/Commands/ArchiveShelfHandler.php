<?php

namespace Shop\Roles\Commands;

use JesseGall\CodeCommandments\Sins\Backend\ConcreteCrossModuleDependency;
use JesseGall\CodeCommandments\Testing\Righteous;
use Shop\Roles\Support\ShelfArchive;

/**
 * A command handler taking a concrete support service of its own package: Commands and Support are role folders of
 * one PSR-4 root, Shop\Roles, so the dependency stays inside the package.
 */
final class ArchiveShelfHandler
{
    #[Righteous(ConcreteCrossModuleDependency::class)]
    public function __construct(private readonly ShelfArchive $archive) {}

    public function handle(string $shelf): bool
    {
        if (! $this->archive->holds($shelf)) {
            $this->archive->archive($shelf);
        }

        return $this->archive->holds($shelf);
    }
}
