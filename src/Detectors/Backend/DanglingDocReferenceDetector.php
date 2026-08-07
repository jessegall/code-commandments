<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Backend;

use JesseGall\CodeCommandments\Ast\AstNode;
use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Backend\Detector;
use JesseGall\CodeCommandments\Sins\Backend\DanglingDocReference;
use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Support\ClassName;

/**
 * A docblock cross-reference (`{@see …}` / `{@link …}`) that points at a FIRST-PARTY class the codebase does
 * not declare — a name that was renamed or removed, its documentation left dangling. Only references sharing
 * the enclosing class's vendor root are judged: a `{@see}` into another package can't be verified from here
 * and is left alone. Documentation must point at what the code actually IS.
 */
final class DanglingDocReferenceDetector implements Detector
{
    public function sin(): Sin
    {
        return new DanglingDocReference();
    }

    public function find(Codebase $codebase): array
    {
        $findings = [];

        foreach ([...$codebase->whereClass()->get(), ...$codebase->whereMethodDeclaration()->get()] as $node) {
            if ($this->hasDanglingReference($node, $codebase)) {
                $findings[] = $node;
            }
        }

        return $findings;
    }

    private function hasDanglingReference(AstNode $node, Codebase $codebase): bool
    {
        $root = ClassName::root($node->enclosingClassName());

        if ($root === '') {
            return false;
        }

        foreach ($node->docReferences() as $reference) {
            if (ClassName::root($reference) !== $root) {
                continue; // another vendor's namespace — not verifiable from this codebase
            }

            if ($codebase->declarationMatch($reference) === null) {
                return true; // first-party, but no such class is declared
            }
        }

        return false;
    }
}
