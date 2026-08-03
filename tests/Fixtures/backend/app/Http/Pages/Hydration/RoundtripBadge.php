<?php

namespace Shop\Http\Pages\Hydration;

use JesseGall\CodeCommandments\Sins\Backend\Spatie\DataToArrayRoundtrip;
use JesseGall\CodeCommandments\Testing\Fixed;
use JesseGall\CodeCommandments\Testing\Sinful;
use Spatie\LaravelData\Data;

/*
 * N4 scenario 1 — a `Data` object arrayed straight into a slot typed as that same `Data`, which re-hydrates
 * it. Build → array → build. The receiver is a ready object.
 */
final class BadgeHolder extends Data
{
    public function __construct(public readonly BadgeCopy $badge) {}
}

final class BadgeHolderBuilder
{
    #[Sinful(DataToArrayRoundtrip::class)]
    public function hold(BadgeCopy $badge, string $status): BadgeHolder
    {
        $toned = new BadgeCopy($badge->label, $this->toneFor($status));

        return BadgeHolder::from(['badge' => $toned->toArray()]);
    }

    /**
     * The FIX: the `badge` slot is typed `BadgeCopy`, so it takes the object as-is — no `->toArray()`,
     * no rebuild.
     */
    #[Fixed(DataToArrayRoundtrip::class)]
    public function holdReady(BadgeCopy $badge, string $status): BadgeHolder
    {
        $toned = new BadgeCopy($badge->label, $this->toneFor($status));

        return BadgeHolder::from(['badge' => $toned]);
    }

    private function toneFor(string $status): string
    {
        return match ($status) {
            'ok' => 'positive',
            'warn' => 'caution',
            'fail' => 'negative',
            default => 'neutral',
        };
    }
}
