<?php

namespace Shop\Http\Pages\Hydration;

use JesseGall\CodeCommandments\Sins\Backend\Spatie\RedundantNestedFrom;
use JesseGall\CodeCommandments\Testing\Fixed;
use JesseGall\CodeCommandments\Testing\Sinful;
use Spatie\LaravelData\Data;

/*
 * N1 scenario 1 — a single nested `Data` property wrapped in `BadgeCopy::from([...])` where the `badge`
 * slot auto-hydrates the array. Built through a small formatting helper.
 */
#[Fixed(RedundantNestedFrom::class)]
final class BadgeStrip extends Data
{
    public function __construct(public readonly BadgeCopy $badge) {}
}

final class BadgeStripBuilder
{
    #[Sinful(RedundantNestedFrom::class)]
    public function build(int $count): BadgeStrip
    {
        return BadgeStrip::from(['badge' => BadgeCopy::from(['label' => $this->pluralise($count), 'tone' => 'info'])]);
    }

    /**
     * The FIX: the plain array goes straight into the `badge` slot — the parent `::from` hydrates the
     * nested `BadgeCopy` itself.
     */
    #[Fixed(RedundantNestedFrom::class)]
    public function buildPlain(int $count): BadgeStrip
    {
        return BadgeStrip::from(['badge' => ['label' => $this->pluralise($count), 'tone' => 'info']]);
    }

    private function pluralise(int $count): string
    {
        return $count === 1 ? '1 item' : "{$count} items";
    }
}
