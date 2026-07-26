<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Backend;

use JesseGall\CodeCommandments\Ast\AstNode;
use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Ast\NodeMatch;
use JesseGall\CodeCommandments\Backend\Detector;
use JesseGall\CodeCommandments\Sins\Backend\NarratedCommand;
use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Support\VerbMood;

/**
 * A command whose name narrates instead of ordering — `hides()` where `hide()` belongs. Two signals
 * must agree before anything is said: the AST must show a COMMAND (it returns nothing, or returns
 * itself, so calling it is an instruction), and the name's first word must be the third person of a
 * verb the lexicon KNOWS ({@see VerbMood}). A plural-noun getter fails both tests, and a name the
 * author never chose — a parent's or an interface's — is never judged. Points at method-mood.
 */
final class NarratedCommandDetector implements Detector
{
    public function sin(): Sin
    {
        return new NarratedCommand();
    }

    /**
     * Is this a fluent SPECIFICATION rather than an order — `->startsWith('a')`, `->compliesWith($fn)`?
     * A self-returning method that relates the receiver to what it is handed is stating a constraint,
     * and English states those in the third person. A void method with the same shape is still an
     * order, so only the fluent form is excused.
     */
    private function declaresAConstraint(AstNode $node): bool
    {
        if ($node->isCommandMethod() && in_array($node->declaredReturnType(), ['void', 'never'], true)) {
            return false;
        }

        if ($node->takesNoArguments()) {
            return false;
        }

        return VerbMood::isRelationalCompound($node->methodName());
    }

    public function find(Codebase $codebase): array
    {
        return $codebase
            ->whereMethodDeclaration()
            ->reject(fn (AstNode $n): bool => $n->isMagicMethod())
            ->reject(fn (NodeMatch $n): bool => $n->nameIsInherited())
            ->where(fn (AstNode $n): bool => $n->isCommandMethod())
            ->where(fn (AstNode $n): bool => VerbMood::isThirdPerson($n->methodName()))
            ->reject(fn (AstNode $n): bool => $this->declaresAConstraint($n))
            ->get();
    }
}
