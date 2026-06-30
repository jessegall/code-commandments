<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Backend;

use JesseGall\CodeCommandments\Sins\Sin;
use JesseGall\CodeCommandments\Sins\Backend\ModelMutationAtCallSite;
use JesseGall\CodeCommandments\Ast\AstNode;
use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Ast\NodeMatch;
use JesseGall\CodeCommandments\Ast\Support\ReceiverResolver;
use JesseGall\CodeCommandments\Detectors\Detector;

/**
 * Setting an Eloquent model's properties then calling `->save()` at a call site —
 * `$order->status = 'paid'; $order->save();`. The mutation has no name and no
 * home; the transition belongs ON the model as an intention-revealing method
 * (`$order->markPaid()`) that owns the fields it touches. Points at laravel-idioms.
 *
 * Gated on the receiver actually being a Model (resolved type extends Eloquent's
 * `Model`): a builder or value object with its own `save()` mutated then saved is
 * not this sin, and `$this->save()` inside the model is the intention method.
 */
final class ModelMutationAtCallSiteDetector implements Detector
{
    private const string MODEL = 'Illuminate\\Database\\Eloquent\\Model';

    public function sin(): Sin
    {
        return new ModelMutationAtCallSite();
    }

    public function find(Codebase $codebase): array
    {
        return $codebase
            ->whereMethod('save')
            ->where(static fn (AstNode $node): bool => $node instanceof NodeMatch && $node->receiverMutatedNearby())
            ->where(static fn (AstNode $node): bool => self::receiverIsModel($codebase, $node))
            ->get();
    }

    private static function receiverIsModel(Codebase $codebase, AstNode $save): bool
    {
        $type = ReceiverResolver::typeOf($save);

        return $type !== null && $codebase->extends($type, self::MODEL);
    }
}
