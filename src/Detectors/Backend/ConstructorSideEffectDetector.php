<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Backend;

use JesseGall\CodeCommandments\Ast\AstNode;
use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Backend\Detector;
use JesseGall\CodeCommandments\Sins\Backend\ConstructorSideEffect;
use JesseGall\CodeCommandments\Sins\Sin;

/**
 * A constructor with a SIDE EFFECT — it calls a collaborator and throws the result away, so the
 * call was made for what it DID: eager-loading a relation, writing a global default, narrowing a
 * query builder the caller still holds. Discarding the result is what makes that structural rather
 * than a matter of intent ({@see AstNode::constructorHasSideEffect}) — asking a collaborator FOR
 * something and building yourself out of it is ordinary construction and reads no differently.
 */
final class ConstructorSideEffectDetector implements Detector
{
    public function sin(): Sin
    {
        return new ConstructorSideEffect();
    }

    public function find(Codebase $codebase): array
    {
        return $codebase
            ->whereClass()
            ->where(static fn (AstNode $node): bool => $node->constructorHasSideEffect())
            ->get();
    }
}
