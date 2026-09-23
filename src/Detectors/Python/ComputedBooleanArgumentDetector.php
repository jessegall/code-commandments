<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Python;

use JesseGall\CodeCommandments\Py\Codebase;
use JesseGall\CodeCommandments\Py\ExprMatch;
use JesseGall\CodeCommandments\Py\Node\ClassDef;
use JesseGall\CodeCommandments\Py\Node\FunctionDef;
use JesseGall\CodeCommandments\Py\NodeMatch;
use JesseGall\CodeCommandments\Python\Detector;
use JesseGall\CodeCommandments\Sins\Python\ComputedBooleanArgument;
use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\WholeTree;

/**
 * A method of bools every caller derives from one object — the twin of the backend's
 * {@see \JesseGall\CodeCommandments\Detectors\Backend\ComputedBooleanArgumentDetector}. The callers' receivers
 * are typed by mypy; a caller whose arguments it could not type proves nothing, and quiets the rule.
 */
final class ComputedBooleanArgumentDetector implements Detector, WholeTree
{
    /**
     * The decision must be re-derived at this many call sites before they can drift apart.
     */
    private const int MIN_CALLERS = 2;

    public function sin(): Sin
    {
        return new ComputedBooleanArgument();
    }

    public function find(Codebase $codebase): array
    {
        return $codebase
            ->whereMethodDeclaration()
            ->where(static fn (NodeMatch $match): bool => $match->decidesOnBoolsAlone())
            ->where(fn (NodeMatch $match) => $this->callersAllAskOneObject($match, $codebase))
            ->get();
    }

    /**
     * Do at least {@see MIN_CALLERS} call sites derive every flag from one object, all of the same class, and
     * does one of them sit outside the method's own class? A helper only its own class calls is the rule
     * already living in one place.
     */
    private function callersAllAskOneObject(NodeMatch $declaration, Codebase $codebase): bool
    {
        $method = $declaration->node;
        $class = $declaration->module->ancestorsOf($method)[1] ?? null;

        if (! $method instanceof FunctionDef || ! $class instanceof ClassDef) {
            return false;
        }

        $callers = $codebase->index()->callersOf($method);
        $subjects = [];

        foreach ($callers as $call) {
            $subject = $call->argumentSubjectType($codebase);

            if ($subject->isNone()) {
                return false;
            }

            $subjects[$subject->unwrap()] = true;
        }

        $fromOutside = array_any($callers, static fn (ExprMatch $call): bool => ! $call->module->classOf($call->expr)->isSomeAnd(static fn (ClassDef $caller): bool => $caller === $class));

        return $fromOutside && count($callers) >= self::MIN_CALLERS && count($subjects) === 1;
    }
}
