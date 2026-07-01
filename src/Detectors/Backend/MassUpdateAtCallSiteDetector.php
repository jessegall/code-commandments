<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Backend;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Sins\Backend\MassUpdateAtCallSite;
use JesseGall\CodeCommandments\Ast\AstNode;
use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Ast\NodeMatch;
use JesseGall\CodeCommandments\Ast\Support\ReceiverResolver;
use JesseGall\CodeCommandments\Backend\Detector;

/**
 * A bare `$model->update([...])` on an Eloquent model at a call site — an
 * anonymous array of column writes with no name and no home. The transition
 * belongs ON the model as an intention-revealing method (`$run->markClosed()`)
 * that owns the fields it touches. Points at laravel-idioms.
 *
 * `$this->update([...])` inside the model IS that intention method, so it is left
 * alone; the receiver must resolve to a Model for a hit.
 */
final class MassUpdateAtCallSiteDetector implements Detector
{
    private const string MODEL = 'Illuminate\\Database\\Eloquent\\Model';

    public function sin(): Sin
    {
        return new MassUpdateAtCallSite();
    }

    public function find(Codebase $codebase): array
    {
        return $codebase
            ->whereMethod('update')
            ->where(static fn (AstNode $node): bool => $node->isMassArrayUpdate())
            ->where(static fn (AstNode $node): bool => $node instanceof NodeMatch && $codebase->extends(ReceiverResolver::typeOf($node), self::MODEL))
            ->get();
    }
}
