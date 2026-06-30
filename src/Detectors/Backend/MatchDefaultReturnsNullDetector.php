<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Backend;

use JesseGall\CodeCommandments\Ast\AstNode;
use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Detectors\Detector;

/**
 * A `match` whose `default` arm returns `null`/`false`/`[]` instead of throwing.
 * An unmatched case is a hole in a supposedly-closed set; swallowing it into an
 * absence value hides the bug. The default should throw a named exception so a
 * new case fails loudly. Points at enums-with-behaviour.
 */
final class MatchDefaultReturnsNullDetector implements Detector
{
    public function skill(): string
    {
        return 'backend/enums-with-behaviour';
    }

    public function find(Codebase $codebase): array
    {
        return $codebase
            ->where(static fn (AstNode $node): bool => $node->isMatchWithAbsenceDefault())
            ->get();
    }
}
