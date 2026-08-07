<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Backend;

use JesseGall\CodeCommandments\Ast\AstNode;
use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Backend\Detector;
use JesseGall\CodeCommandments\Sins\Backend\TypeSwitch;
use JesseGall\CodeCommandments\Sins\Sin;

/**
 * A run of `instanceof` tests on the same subject, each deciding a different branch — the caller
 * asking a value what it IS so it can decide what to do, when the value could simply be told. The
 * knowledge that belongs to each type ends up outside all of them, and every new type means finding
 * every ladder again; the interface, by contrast, cannot be forgotten. Spelling is irrelevant
 * ({@see AstNode::isTypeSwitchHead}) — a ladder, sequential `if`s and a `match (true)` are one sin,
 * while a single test is narrowing and a union in ONE condition asks one question.
 */
final class TypeSwitchDetector implements Detector
{
    public function sin(): Sin
    {
        return new TypeSwitch();
    }

    public function find(Codebase $codebase): array
    {
        return $codebase
            ->where(static fn (AstNode $node): bool => $node->isTypeSwitchHead())
            ->where(fn (AstNode $node) => $this->everyTypeIsOwned($node, $codebase))
            ->get();
    }

    /**
     * Does the codebase DECLARE every type the switch tests? The fix is a method on the shared
     * abstraction, so it is only available to someone who owns the types — a ladder over `DOMText`
     * and `DOMElement`, or over a parser's node classes, is the only way to handle them in PHP and
     * is not this sin. Judging a subtree can only under-report here, never over-report.
     */
    private function everyTypeIsOwned(AstNode $node, Codebase $codebase): bool
    {
        foreach ($node->typeSwitchClasses() as $class) {
            if ($codebase->declarationMatch($class) === null) {
                return false;
            }
        }

        return true;
    }
}
