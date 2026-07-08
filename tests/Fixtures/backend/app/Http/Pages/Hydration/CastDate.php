<?php

namespace Shop\Http\Pages\Hydration;

use JesseGall\CodeCommandments\Sins\Backend\Spatie\RedundantNativeCast;
use JesseGall\CodeCommandments\Testing\Sinful;
use Spatie\LaravelData\Data;

/*
 * N3 scenario 2 — a `new DateTime` at a `DateTimeInterface` slot Spatie auto-casts from the raw string.
 */
final class AuditEntry extends Data
{
    public function __construct(public readonly string $action, public readonly \DateTimeInterface $at) {}
}

final class AuditEntryFactory
{
    #[Sinful(RedundantNativeCast::class)]
    public function record(string $action, string $at): AuditEntry
    {
        return AuditEntry::from(['action' => $action, 'at' => new \DateTime($at)]);
    }
}
