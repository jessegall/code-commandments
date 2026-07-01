<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Backend\Laravel;

use JesseGall\CodeCommandments\Ast\AstNode;
use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Ast\Laravel\LaravelNode;
use JesseGall\CodeCommandments\Backend\Detector;
use JesseGall\CodeCommandments\Sins\Backend\Laravel\MassUpdateAtCallSite;
use JesseGall\CodeCommandments\Sins\Sin;

/**
 * A mass `->update([...])` on an Eloquent model at a call site — an untyped array of attributes
 * smuggling a mutation past the model's own methods. Give the change an intention-revealing method
 * on the model instead. Points at laravel-idioms. The receiver is confirmed a Model by type.
 */
final class MassUpdateAtCallSiteDetector implements Detector
{
    public function sin(): Sin
    {
        return new MassUpdateAtCallSite();
    }

    public function find(Codebase $codebase): array
    {
        return $codebase
            ->whereMethod('update')
            ->where(static fn (AstNode $node): bool => $node->isMassArrayUpdate())
            ->where(static fn (LaravelNode $node): bool => $node->receiverIsModel())
            ->get();
    }
}
