<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Backend;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Sins\Backend\ConstClassEnum;
use JesseGall\CodeCommandments\Ast\AstNode;
use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Backend\Detector;

/**
 * A class that is nothing but scalar constants — a closed set of values hand-
 * rolled as `const STATUS_PENDING = 'pending'` instead of a native backed enum.
 * The enum seals the set in the type and gives the cases a home for behaviour.
 * A SUBCLASS is left alone: PHP enums extend nothing, so there is no enum for it
 * to become. Points at enums-with-behaviour.
 */
final class ConstClassEnumDetector implements Detector
{
    public function sin(): Sin
    {
        return new ConstClassEnum();
    }

    public function find(Codebase $codebase): array
    {
        return $codebase
            ->whereClass()
            ->where(static fn (AstNode $node): bool => $node->isScalarConstClass())
            ->reject(static fn (AstNode $node): bool => $node->extendsAClass())
            ->get();
    }
}
