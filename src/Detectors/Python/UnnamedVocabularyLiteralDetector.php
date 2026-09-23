<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Python;

use JesseGall\CodeCommandments\Py\Codebase;
use JesseGall\CodeCommandments\Py\ConstantVocabulary;
use JesseGall\CodeCommandments\Py\Expr\ExprKind;
use JesseGall\CodeCommandments\Py\Expr\LiteralType;
use JesseGall\CodeCommandments\Py\ExprMatch;
use JesseGall\CodeCommandments\Python\Detector;
use JesseGall\CodeCommandments\Sins\Python\UnnamedVocabularyLiteral;
use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\WholeTree;

/**
 * A raw string where the codebase elsewhere spells the same parameter by name — the twin of the backend's
 * {@see \JesseGall\CodeCommandments\Detectors\Backend\UnnamedVocabularyLiteralDetector}, decided by
 * {@see ConstantVocabulary}. It flags the literal, found through the call that carries it.
 */
final class UnnamedVocabularyLiteralDetector implements Detector, WholeTree
{
    public function sin(): Sin
    {
        return new UnnamedVocabularyLiteral();
    }

    public function find(Codebase $codebase): array
    {
        $vocabulary = new ConstantVocabulary($codebase);
        $findings = [];

        foreach ($codebase->whereCall()->get() as $call) {
            foreach ($call->expr->get('arguments') as $argument) {
                $literal = $argument->is(ExprKind::Keyword) ? $argument->get('value') : $argument;

                if ($literal->literalType() === LiteralType::String && $vocabulary->nameFor($call, $literal)->isSome()) {
                    $findings[] = new ExprMatch($literal, $call->module);
                }
            }
        }

        return $findings;
    }
}
