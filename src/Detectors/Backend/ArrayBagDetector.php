<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Backend;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Sins\Backend\ArrayBag;
use JesseGall\CodeCommandments\Ast\AstNode;
use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Backend\Detector;
use JesseGall\CodeCommandments\Packages\AppliesExemptions;
use JesseGall\CodeCommandments\Packages\ExemptBy;
use JesseGall\CodeCommandments\Packages\Exemptable;
use JesseGall\CodeCommandments\Packages\Tags\NoContainer;

/**
 * An `array` read by string-literal keys — a structured bag that should be a typed value
 * object. Dynamic/positional keys are genuine maps/tuples (left alone). A `NoContainer` type
 * (an Eloquent cast) is exempt — the framework dictates its array parameter.
 */
final class ArrayBagDetector implements Detector, Exemptable
{
    use AppliesExemptions;

    public function sin(): Sin
    {
        return new ArrayBag();
    }

    public function exemptions(): array
    {
        return [NoContainer::class => [ExemptBy::EnclosingClass]];
    }

    public function find(Codebase $codebase): array
    {
        return $this->exempt($codebase
            ->where(static fn (AstNode $node): bool => $node->arrayKeyIsString())
            ->where(static fn (AstNode $node): bool => $node->enclosingParamIsArray($node->arrayBaseName() ?? ''))
            ->get(), $codebase);
    }
}
