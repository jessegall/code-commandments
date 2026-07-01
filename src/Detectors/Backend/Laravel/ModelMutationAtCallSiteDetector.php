<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Backend\Laravel;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Ast\Laravel\LaravelNode;
use JesseGall\CodeCommandments\Backend\Detector;
use JesseGall\CodeCommandments\Sins\Backend\Laravel\ModelMutationAtCallSite;
use JesseGall\CodeCommandments\Sins\Sin;

/**
 * Setting an Eloquent model's properties at a call site and then `->save()`-ing it — the mutation
 * belongs behind an intention-revealing method on the model (`$order->markPaid()`), not smeared
 * across the caller. Points at laravel-idioms. The receiver is confirmed a Model by type, and only
 * flagged when the same receiver is written to nearby (`$this->save()` inside the model is exempt).
 */
final class ModelMutationAtCallSiteDetector implements Detector
{
    public function sin(): Sin
    {
        return new ModelMutationAtCallSite();
    }

    public function find(Codebase $codebase): array
    {
        return $codebase
            ->whereMethod('save')
            ->where(static fn (LaravelNode $node): bool => $node->receiverMutatedNearby())
            ->where(static fn (LaravelNode $node): bool => $node->receiverIsModel())
            ->get();
    }
}
