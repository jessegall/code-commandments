<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\CSharp;

use JesseGall\CodeCommandments\Cs\Codebase;
use JesseGall\CodeCommandments\Cs\Node;
use JesseGall\CodeCommandments\Cs\NodeMatch;
use JesseGall\CodeCommandments\CSharp\Detector;
use JesseGall\CodeCommandments\Sins\CSharp\BareStatePredicate;
use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Support\VerbMood;

/**
 * A `bool` about the object alone, named as a third-person claim — the C# twin of the PHP and Python
 * bare-state-predicate rules. The name is read by the one shared lexicon, {@see VerbMood}.
 */
final class BareStatePredicateDetector implements Detector
{
    public function sin(): Sin
    {
        return new BareStatePredicate();
    }

    public function find(Codebase $codebase): array
    {
        return $codebase
            ->whereNode(static fn (Node $member): bool => $member->isStatePredicate())
            ->where(static fn (NodeMatch $match) => VerbMood::isThirdPerson($match->node->name))
            ->reject(static fn (NodeMatch $match) => VerbMood::readsAsQuestion($match->node->name))
            ->reject(static fn (NodeMatch $match): bool => $match->isOverride())
            ->get();
    }
}
