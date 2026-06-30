<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Backend;

use JesseGall\CodeCommandments\Ast\AstNode;
use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Detectors\Detector;

/**
 * A method that SAVES one of its own properties to a local and RESTORES it
 * afterwards — `$prev = $this->scope; … $this->scope = $prev;`. That dance only
 * makes sense because the field is per-call scratch state, mutated for the
 * duration of the call (and the type can't say so). The data is really an input:
 * pass it as a parameter or a per-call value object, and the field — with its
 * save, restore, and null-guards — disappears. Points at type-honesty.
 */
final class ScratchStateRestoreDetector implements Detector
{
    public function skill(): string
    {
        return 'backend/type-honesty';
    }

    public function find(Codebase $codebase): array
    {
        return $codebase
            ->whereMethodDeclaration()
            ->where(static fn (AstNode $node): bool => $node->savesAndRestoresOwnState())
            ->get();
    }
}
