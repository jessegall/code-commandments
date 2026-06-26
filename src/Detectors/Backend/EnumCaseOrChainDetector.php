<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Backend;

use JesseGall\CodeCommandments\Ast\AstNode;
use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Ast\Support\Enums;
use JesseGall\CodeCommandments\Detectors\Detector;

/**
 * `$x === Status::Pending || $x === Status::Paid` — a hand-rolled membership test
 * against two-or-more cases of the same backed enum. The set is a concept; give
 * it a name as a case-group method on the enum (`$x->isOpen()`) instead of
 * re-deriving it at every call site. Points at enums-with-behaviour.
 */
final class EnumCaseOrChainDetector implements Detector
{
    public function skill(): string
    {
        return 'enums-with-behaviour';
    }

    public function find(Codebase $codebase): array
    {
        $enums = Enums::casesByEnum($codebase);

        return $codebase
            ->where(static function (AstNode $node) use ($enums): bool {
                $class = $node->orChainComparedClass();

                return $class !== null && isset($enums[ltrim($class, '\\')]);
            })
            ->get();
    }
}
