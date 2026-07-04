<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Backend\Laravel;

use JesseGall\CodeCommandments\Ast\AstNode;
use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Ast\Laravel\LaravelNode;
use JesseGall\CodeCommandments\Backend\Detector;
use JesseGall\CodeCommandments\Sins\Backend\Laravel\FacadeCall;
use JesseGall\CodeCommandments\Sins\Sin;

/**
 * Detects Laravel facade calls that hide dependencies; inject the underlying contract instead.
 */
final class FacadeCallDetector implements Detector
{
    public function sin(): Sin
    {
        return new FacadeCall();
    }

    public function find(Codebase $codebase): array
    {
        return $codebase
            ->whereStaticCall()
            ->where(static fn (LaravelNode $node): bool => $node->isFacadeCall())
            ->reject(static fn (AstNode $node): bool => $node->staticCallMethodIs('fake'))
            ->reject(static fn (AstNode $node): bool => $node->isOutsideClass())
            ->reject(static fn (LaravelNode $node): bool => $node->inServiceProvider())
            ->reject(static fn (LaravelNode $node): bool => $node->isEloquentCast())
            ->get();
    }
}
