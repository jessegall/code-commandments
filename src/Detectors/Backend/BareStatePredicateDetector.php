<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Backend;

use JesseGall\CodeCommandments\Ast\AstNode;
use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Ast\NodeMatch;
use JesseGall\CodeCommandments\Backend\Detector;
use JesseGall\CodeCommandments\Sins\Backend\BareStatePredicate;
use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Support\VerbMood;

/**
 * A `bool` about the object's OWN state, named as a bare verb — `binds()` where `isBound()` belongs.
 * The parameter list is what separates it from a relational predicate: `contains($item)` compares the
 * receiver with something, so the third person is correct English, while a no-argument predicate can
 * only be describing the receiver — and that is what a question is for. Points at method-mood.
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
            ->whereMethodDeclaration()
            ->reject(fn (AstNode $n): bool => $n->isMagicMethod())
            ->reject(fn (NodeMatch $n): bool => $n->nameIsInherited())
            ->where(fn (AstNode $n): bool => $n->returnsBool())
            ->where(fn (AstNode $n): bool => $n->takesNoArguments())
            ->reject(fn (AstNode $n) => VerbMood::readsAsQuestion($n->methodName()))
            ->where(fn (AstNode $n) => VerbMood::isThirdPerson($n->methodName()))
            ->get();
    }
}
