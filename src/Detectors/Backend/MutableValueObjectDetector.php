<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Backend;

use JesseGall\CodeCommandments\Ast\AstNode;
use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Backend\Detector;
use JesseGall\CodeCommandments\Sins\Backend\MutableValueObject;
use JesseGall\CodeCommandments\Sins\Sin;

/**
 * A VALUE that can change after it is built. Equality, sharing and caching all rest on the same
 * promise — that this value stays the value it is — so one method writing `$this->field` breaks
 * every holder of it at once, at a distance, invisibly. The fix is not to guard the write but to
 * derive: build a new instance and let the old one keep being what it was. Value-vs-machine is
 * walked rather than guessed — {@see Codebase::classIsValueType} for the fields it holds, and
 * {@see AstNode::mutatesOwnFieldsAfterConstruction} for whether those fields are what the caller
 * ASKED for, since a class made of scalars but keeping working state is a machine, not a value.
 */
final class MutableValueObjectDetector implements Detector
{
    public function sin(): Sin
    {
        return new MutableValueObject();
    }

    public function find(Codebase $codebase): array
    {
        return $codebase
            ->whereClass()
            ->where(static fn (AstNode $node): bool => $codebase->classIsValueType($node->enclosingClassName()))
            ->where(static fn (AstNode $node): bool => $node->mutatesOwnFieldsAfterConstruction($codebase->traitMethodsOf($node->enclosingClassName())))
            ->get();
    }
}
