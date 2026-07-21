<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Backend\Laravel;

use JesseGall\CodeCommandments\Ast\AstNode;
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
 * nothing. Points at laravel-idioms.
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
            ->reject(static fn (LaravelNode $node): bool => self::isFireable($codebase, $node->listenedEventClass() ?? ''))
            ->get();
    }

    /**
     * Can anything raise this event? An event is always CONSTRUCTED to be dispatched, so a `new X`
     * anywhere is proof; `X::dispatch()` is the constructor-free spelling of the same thing. Either
     * on the event itself or on any subclass of it — a listener bound to a base event answers all of
     * its children, so a fired child keeps the base's wiring alive.
     */
    private static function isFireable(Codebase $codebase, string $event): bool
    {
        return $codebase
            ->whereNew()
            ->where(static fn (AstNode $node): bool => self::isEventOrChild($codebase, $node->newClassName(), $event))
            ->count() > 0
            || $codebase
                ->whereStaticCall()
                ->where(static fn (AstNode $node): bool => in_array($node->staticCallMethod() ?? '', LaravelNode::EVENT_DISPATCHERS, true))
                ->where(static fn (AstNode $node): bool => self::isEventOrChild($codebase, $node->staticCallClass(), $event))
                ->count() > 0;
    }

    private static function isEventOrChild(Codebase $codebase, ?string $candidate, string $event): bool
    {
        return $candidate !== null && ($candidate === $event || $codebase->extends($candidate, $event) || $codebase->implements($candidate, $event));
    }
}
