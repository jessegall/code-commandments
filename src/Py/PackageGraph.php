<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Py;

/**
 * Which of a codebase's packages import which — each package a folder holding an `__init__.py`, each arrow
 * an import from a module in one package reaching a module in another. The Python twin of the backend's
 * {@see \JesseGall\CodeCommandments\Ast\Support\NamespaceGraph}.
 */
final class PackageGraph
{
    /**
     * @var list<array{NodeMatch, string, string}>  each import, the package it is written in, the package it reaches
     */
    private array $arrows = [];

    public function __construct(Codebase $codebase)
    {
        $packages = array_fill_keys(array_map(static fn (ModuleFile $module): string => dirname($module->file), array_filter($codebase->modules(), static fn (ModuleFile $module): bool => basename($module->file) === '__init__.py')), true);

        foreach ($codebase->modules() as $module) {
            $from = dirname($module->file);

            if (! isset($packages[$from])) {
                continue;
            }

            foreach ($codebase->index()->importsOf($module) as [$import, $reached]) {
                $to = dirname($reached->file);

                if ($to !== $from && isset($packages[$to])) {
                    $this->arrows[] = [new NodeMatch($import, $module), $from, $to];
                }
            }
        }
    }

    /**
     * Would $referrer, reaching into $target, close a cycle — does $target's package already import
     * $referrer's? Then the reach is the arrow back that another rule forbids.
     */
    public function wouldCloseACycle(ModuleFile $referrer, ModuleFile $target): bool
    {
        $from = dirname($referrer->file);
        $to = dirname($target->file);

        return $from !== $to && array_any($this->arrows, static fn (array $arrow): bool => $arrow[1] === $to && $arrow[2] === $from);
    }

    /**
     * The imports of the direction worth cutting in every mutual pair — the thinner of the two, the one with
     * fewer imports, ties broken on the name so a codebase always yields the same answer. One per module and
     * package it reaches, each named where it is written.
     *
     * @return list<NodeMatch>
     */
    public function arrowsClosingAMutualPair(): array
    {
        $count = [];

        foreach ($this->arrows as [, $from, $to]) {
            $count["{$from}\0{$to}"] = ($count["{$from}\0{$to}"] ?? 0) + 1;
        }

        $closing = [];

        foreach ($this->arrows as [$import, $from, $to]) {
            $back = $count["{$to}\0{$from}"] ?? 0;

            if ($back > 0 && ([$count["{$from}\0{$to}"], $from] <=> [$back, $to]) <= 0) {
                $closing[$import->file() . "\0" . $to] ??= $import;
            }
        }

        return array_values($closing);
    }
}
