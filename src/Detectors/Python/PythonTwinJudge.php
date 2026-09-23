<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Python;

use JesseGall\CodeCommandments\Ast\Support\ReachedUnit;
use JesseGall\CodeCommandments\Detectors\TwinJudge;
use JesseGall\CodeCommandments\Py\Codebase;
use JesseGall\CodeCommandments\Py\Expr\Expr;
use JesseGall\CodeCommandments\Py\Node\FunctionDef;
use JesseGall\CodeCommandments\Py\NodeMatch;

/**
 * What Python answers for {@see \JesseGall\CodeCommandments\Detectors\DivergentTwins}: units are functions,
 * keyed where they are declared, over the codebase's {@see \JesseGall\CodeCommandments\Py\ResourceReach}.
 */
final readonly class PythonTwinJudge implements TwinJudge
{
    public function __construct(private Codebase $codebase) {}

    public function isType(string $resource): bool
    {
        return $this->codebase->resourceReach()->isType($resource);
    }

    /**
     * The same method answering a contract both classes inherit — each overriding a base's declaration.
     *
     * @param  ReachedUnit<NodeMatch>  $poorer
     * @param  ReachedUnit<NodeMatch>  $richer
     */
    public function arePolymorphicSiblings(ReachedUnit $poorer, ReachedUnit $richer): bool
    {
        return $poorer->match->name() === $richer->match->name()
            && $this->overrides($poorer->match)
            && $this->overrides($richer->match);
    }

    /**
     * Both declare a result, and they cannot be one value: a class of the project's beside a builtin, two
     * unrelated classes, or two different builtins.
     *
     * @param  ReachedUnit<NodeMatch>  $poorer
     * @param  ReachedUnit<NodeMatch>  $richer
     */
    public function resultsAreIncomparable(ReachedUnit $poorer, ReachedUnit $richer): bool
    {
        $one = $poorer->match->node instanceof FunctionDef ? $poorer->match->node->returnedTypeName() : '';
        $other = $richer->match->node instanceof FunctionDef ? $richer->match->node->returnedTypeName() : '';

        if ($one === '' || $other === '' || $one === $other) {
            return false;
        }

        $oneClass = $this->codebase->classNamed($one);
        $otherClass = $this->codebase->classNamed($other);

        if ($oneClass->isNone() || $otherClass->isNone()) {
            return true;
        }

        return ! $this->derives($oneClass->unwrap()->bases, $other) && ! $this->derives($otherClass->unwrap()->bases, $one);
    }

    /**
     * @param  ReachedUnit<NodeMatch>  $unit
     */
    public function callersOf(ReachedUnit $unit): array
    {
        if (! $unit->match->node instanceof FunctionDef) {
            return [];
        }

        $callers = [];

        foreach ($this->codebase->index()->callersOf($unit->match->node) as $call) {
            $call->module->functionOf($call->expr)->inspect(function (FunctionDef $caller) use (&$callers): void {
                $callers[$this->codebase->index()->declarationOf($caller)] = true;
            });
        }

        return array_keys($callers);
    }

    private function overrides(NodeMatch $match): bool
    {
        return $match->node instanceof FunctionDef && $this->codebase->index()->isOverride($match->node, $match->module);
    }

    /**
     * Does a class with $bases derive directly from the class named $name?
     *
     * @param  list<Expr>  $bases
     */
    private function derives(array $bases, string $name): bool
    {
        return array_any($bases, static fn (Expr $base): bool => array_last(explode('.', $base->dottedName())) === array_last(explode('.', $name)));
    }
}
