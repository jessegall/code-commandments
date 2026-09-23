<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Python;

use JesseGall\CodeCommandments\Detectors\Backend\Config\NamespaceDependencyConfig;
use JesseGall\CodeCommandments\Py\Codebase;
use JesseGall\CodeCommandments\Py\NodeMatch;
use JesseGall\CodeCommandments\Python\Detector;
use JesseGall\CodeCommandments\Sins\Python\NamespaceDependency;
use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\WholeTree;

/**
 * An import out of a declared layer into a package it may not use — the twin of the backend's
 * {@see \JesseGall\CodeCommandments\Detectors\Backend\NamespaceDependencyDetector}, over dotted package
 * names. Only what the project declared is judged: a package never declared, and a module in none, are free.
 */
final class NamespaceDependencyDetector implements Detector, WholeTree
{
    use NamespaceDependencyConfig;

    /**
     * How Python spells a qualified name: `.` between the parts, case counting.
     */
    private const string SEPARATOR = '.';

    private const bool CASE_SENSITIVE = true;

    public function sin(): Sin
    {
        return new NamespaceDependency();
    }

    public function find(Codebase $codebase): array
    {
        $stack = $this->stack();

        if ($stack->isEmpty()) {
            return [];
        }

        $findings = [];

        foreach ($codebase->modules() as $module) {
            $from = $stack->layerOf($codebase->fullNameOf($module));

            if ($from === null) {
                continue;
            }

            foreach ($codebase->index()->importsOf($module) as [$import, $reached]) {
                $target = $codebase->fullNameOf($reached);

                if ($stack->layerOf($target) === null || $stack->mayReference($from, $target)) {
                    continue;
                }

                // One finding per module and module it reaches, at its first import.
                $findings[$module->file . "\0" . $target] ??= new NodeMatch($import, $module);
            }
        }

        return array_values($findings);
    }
}
