<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Backend\Laravel;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Ast\Laravel\LaravelNode;
use JesseGall\CodeCommandments\Backend\Detector;
use JesseGall\CodeCommandments\Sins\Backend\Laravel\DeadEventWiring;
use JesseGall\CodeCommandments\Sins\Sin;

/**
 * An `Event::listen(X::class, …)` whose event class nothing can fire: X is never constructed and
 * never dispatched statically. The listener chain dead-ends, but the registration reads as live
 * wiring, so it outlives the feature that used to raise the event. A SUBCLASS being fired counts —
 * a listener on a base event answers every child — so only a genuinely unreachable branch is
 * flagged. Only FIRST-PARTY events are judged: a framework or package event (`JobQueued`,
 * `MessageSent`) is raised inside vendor code the scan never sees, so its absence here proves
 * nothing. An INTERFACE-typed listen is likewise spared: it is an extension point — the events that
 * satisfy it are the host application's own, declared outside the scanned tree, so no absence here can
 * prove the branch dead. Points at laravel-idioms.
 */
final class DeadEventWiringDetector implements Detector
{
    public function sin(): Sin
    {
        return new DeadEventWiring();
    }

    public function find(Codebase $codebase): array
    {
        return $codebase
            ->where(static fn (LaravelNode $node): bool => $node->listenedEventClass() !== null)
            ->where(static fn (LaravelNode $node): bool => $codebase->declarationMatch($node->listenedEventClass()) !== null)
            ->reject(static fn (LaravelNode $node): bool => $codebase->isInterface($node->listenedEventClass()))
            ->reject(static fn (LaravelNode $node): bool => $codebase->isEverProduced($node->listenedEventClass(), LaravelNode::EVENT_DISPATCHERS))
            ->get();
    }

}
