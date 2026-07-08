<?php

namespace Shop\Http\Pages\Hydration;

use JesseGall\CodeCommandments\Sins\Backend\Spatie\RedundantNativeCast;
use JesseGall\CodeCommandments\Testing\Sinful;
use Spatie\LaravelData\Data;

/*
 * N3 scenario 1 — a backed enum reconstructed from a code at an enum slot Spatie auto-casts. Built in a
 * class that also resolves a caption.
 */
final class OrderState extends Data
{
    public function __construct(public readonly FulfilmentState $state, public readonly string $caption) {}
}

final class OrderStateBuilder
{
    #[Sinful(RedundantNativeCast::class)]
    public function fromCode(string $code): OrderState
    {
        return OrderState::from(['state' => FulfilmentState::from($code), 'caption' => $this->captionFor($code)]);
    }

    private function captionFor(string $code): string
    {
        return $code === '' ? 'Unknown' : ucfirst($code);
    }
}
