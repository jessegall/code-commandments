<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Backend;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Sins\Backend\MatchDefaultReturnsNull;
use JesseGall\CodeCommandments\Ast\AstNode;
use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Ast\NodeMatch;
use JesseGall\CodeCommandments\Backend\Detector;

/**
 * A `match` whose `default` arm returns `null`/`false`/`[]` instead of throwing.
 * An unmatched case is a hole in a supposedly-closed set; swallowing it into an
 * absence value hides the bug. The default should throw a named exception so a
 * new case fails loudly. Points at enums-with-behaviour. Not flagged: an OPEN
 * (non-enum) subject whose handled arms already admit null — see
 * {@see NodeMatch::matchHandledArmsAdmitNull} (#393).
 */
final class MatchDefaultReturnsNullDetector implements Detector
{
    public function sin(): Sin
    {
        return new MatchDefaultReturnsNull();
    }

    public function find(Codebase $codebase): array
    {
        return $codebase
            ->where(static fn (AstNode $node): bool => $node->isMatchWithAbsenceDefault())
            ->reject(static fn (NodeMatch $node): bool => $node->matchHandledArmsAdmitNull())
            ->get();
    }
}
