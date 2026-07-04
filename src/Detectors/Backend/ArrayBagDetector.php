<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Backend;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Sins\Backend\ArrayBag;
use JesseGall\CodeCommandments\Ast\AstNode;
use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Backend\Detector;
use JesseGall\CodeCommandments\Packages\Exemptions;
use JesseGall\CodeCommandments\Packages\Exemptable;
use JesseGall\CodeCommandments\Packages\Tags\NoContainer;

/**
 * An `array` read by string-literal keys — a structured bag that should be a typed value
 * object. Dynamic/positional keys are genuine maps/tuples (left alone). Eloquent casts
 * are exempt — the framework dictates the `$attributes` parameter.
 */
final class ArrayBagDetector implements Detector, Exemptable
{
    public function sin(): Sin
    {
        return new ArrayBag();
    }

    public function exemptions(): array
    {
        return [NoContainer::class];
    }

    public function find(Codebase $codebase): array
    {
        return $codebase
            ->where(static fn (AstNode $node): bool => $node->arrayKeyIsString())
            ->where(static fn (AstNode $node): bool => $node->enclosingParamIsArray($node->arrayBaseName() ?? ''))
            ->reject(static fn (AstNode $node): bool => Exemptions::has(NoContainer::class, $codebase, $node->enclosingClassName()))
            ->get();
    }
}
