<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\CSharp;

use JesseGall\CodeCommandments\Cs\Codebase;
use JesseGall\CodeCommandments\Cs\NodeMatch;
use JesseGall\CodeCommandments\CSharp\Detector;
use JesseGall\CodeCommandments\Sins\CSharp\UnnamedVocabularyLiteral;
use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\WholeTree;

/**
 * A raw string where the codebase elsewhere spells the same parameter by name — the twin of the backend's and
 * Python's unnamed-vocabulary-literal rules, decided by {@see Codebase::constantNaming}. It flags the literal. A test
 * spelling the raw value is pinning the outside format, which the constant would only compare with itself.
 */
final class UnnamedVocabularyLiteralDetector implements Detector, WholeTree
{
    public function sin(): Sin
    {
        return new UnnamedVocabularyLiteral();
    }

    public function find(Codebase $codebase): array
    {
        $findings = [];

        foreach ($codebase->whereCall()->get() as $call) {
            if (! $call->node->passesByPosition() || $call->module->isTest()) {
                continue;
            }

            foreach ($call->node->arguments() as $position => $literal) {
                if ($literal->is('StringLiteralExpression') && $literal->text !== null && $codebase->constantNaming($call->node, $position, $literal->text)->isSome()) {
                    $findings[] = new NodeMatch($literal, $call->module);
                }
            }
        }

        return $findings;
    }
}
