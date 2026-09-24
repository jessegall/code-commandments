<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\CSharp;

use JesseGall\CodeCommandments\CSharp\Detector;
use JesseGall\CodeCommandments\Cs\Codebase;
use JesseGall\CodeCommandments\Cs\NamespaceGraph;
use JesseGall\CodeCommandments\Detectors\Backend\Config\NamespaceDependencyConfig;
use JesseGall\CodeCommandments\Sins\CSharp\NamespaceDependency;
use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\WholeTree;

/**
 * A reference out of a declared layer into a namespace it did not declare it may use — the twin of the
 * backend's and Python's namespace-dependency rules, over the namespaces the compiler resolved.
 */
final class NamespaceDependencyDetector implements Detector, WholeTree
{
    use NamespaceDependencyConfig;

    /**
     * How C# spells a qualified name: `.` between the parts, case counting.
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

        foreach (new NamespaceGraph($codebase)->arrows()->all as $arrow) {
            $from = $stack->layerOf($arrow->from);

            if ($from === null || $stack->layerOf($arrow->to) === null || $stack->mayReference($from, $arrow->to)) {
                continue;
            }

            // One finding per file and namespace it reaches, at its first reference.
            $findings[$arrow->at->file() . "\0" . $arrow->to] ??= $arrow->at;
        }

        return array_values($findings);
    }
}
