<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Detectors\Backend\Spatie;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Ast\Spatie\SpatieDataNode;
use JesseGall\CodeCommandments\Backend\Detector;
use JesseGall\CodeCommandments\Detectors\Repentable;
use JesseGall\CodeCommandments\Scribes\Backend\RedundantNativeCastScribe;
use JesseGall\CodeCommandments\Sins\Backend\Spatie\RedundantNativeCast;
use JesseGall\CodeCommandments\Sins\Sin;

/**
 * Flags `Enum::from($x)` / `new DateTime($x)` / `Carbon::parse($x)` sitting in a `Data::from([...])` slot
 * typed as that enum or a `DateTimeInterface` — the value Spatie auto-casts from the raw scalar, so the
 * construction is ceremony. A `tryFrom`, a timezone/format second argument, a chained result (the value
 * node isn't the construction), and a `#[WithCast]` slot are all spared.
 */
final class RedundantNativeCastDetector implements Detector, Repentable
{
    public function sin(): Sin
    {
        return new RedundantNativeCast();
    }

    public function scribe(): string
    {
        return RedundantNativeCastScribe::class;
    }

    public function find(Codebase $codebase): array
    {
        return [
            ...$this->gate($codebase->whereStaticCall('from', 'parse')),
            ...$this->gate($codebase->whereNew()),
        ];
    }

    /**
     * @param  \JesseGall\CodeCommandments\Ast\Query  $query
     * @return list<\JesseGall\CodeCommandments\Ast\NodeMatch>
     */
    private function gate($query): array
    {
        return $query
            ->where(static fn (SpatieDataNode $n): bool => $n->constructsNativeCastValue())
            ->where(static fn (SpatieDataNode $n): bool => $n->hasSingleArgument())
            ->where(static fn (SpatieDataNode $n): bool => $n->slotAcceptsNativeCast())
            ->reject(static fn (SpatieDataNode $n): bool => $n->hydrationSlotHasCast())
            ->get();
    }
}
