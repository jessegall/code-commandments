<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Py;

use JesseGall\CodeCommandments\DependencyArrow;
use JesseGall\CodeCommandments\DependencyArrows;
use JesseGall\CodeCommandments\Located;

/**
 * Which of a codebase's packages import which — each package a folder holding an `__init__.py`, each arrow
 * an import from a module in one package reaching a module in another. The Python twin of the backend's
 * {@see \JesseGall\CodeCommandments\Ast\Support\NamespaceGraph}.
 */
final class PackageGraph
{
    /**
     * Each import between two packages — where it is written, its package, the package it reaches.
     */
    private DependencyArrows $arrows;

    public function __construct(Codebase $codebase)
    {
        $arrows = [];
        $packages = array_fill_keys(array_map(static fn (ModuleFile $module): string => dirname($module->file), array_filter($codebase->modules(), static fn (ModuleFile $module): bool => basename($module->file) === '__init__.py')), true);

        foreach ($codebase->modules() as $module) {
            $from = dirname($module->file);

            if (! isset($packages[$from])) {
                continue;
            }

            foreach ($codebase->index()->importsOf($module) as [$import, $reached]) {
                $to = dirname($reached->file);

                if ($to !== $from && isset($packages[$to])) {
                    $arrows[] = new DependencyArrow(new NodeMatch($import, $module), $from, $to);
                }
            }
        }

        $this->arrows = new DependencyArrows($arrows);
    }

    /**
     * Would $referrer, reaching into $target, close a cycle — does $target's package already import
     * $referrer's? Then the reach is the arrow back that another rule forbids.
     */
    public function wouldCloseACycle(ModuleFile $referrer, ModuleFile $target): bool
    {
        $from = dirname($referrer->file);
        $to = dirname($target->file);

        return $from !== $to && $this->arrows->has($to, $from);
    }

    /**
     * The imports of the direction worth cutting in every mutual pair of packages — {@see DependencyArrows::closingAMutualPair}.
     *
     * @return list<Located>
     */
    public function arrowsClosingAMutualPair(): array
    {
        return $this->arrows->closingAMutualPair();
    }
}
