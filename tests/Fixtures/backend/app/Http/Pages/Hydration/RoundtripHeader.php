<?php

namespace Shop\Http\Pages\Hydration;

use JesseGall\CodeCommandments\Sins\Backend\Spatie\DataToArrayRoundtrip;
use JesseGall\CodeCommandments\Testing\Righteous;
use JesseGall\CodeCommandments\Testing\Sinful;
use Spatie\LaravelData\Data;

/*
 * N4 scenario 3 — a fallback receiver (`$header ?? $this->default`) arrayed into a `HeaderCopy` slot that
 * rebuilds it. Plus the righteous twins: a `->toArray()` feeding a genuine array slot (the array IS the
 * value wanted), and a non-`Data` receiver.
 */
final class HeaderHolder extends Data
{
    public function __construct(public readonly HeaderCopy $header) {}
}

final class HeaderHolderMaker
{
    public function __construct(private readonly HeaderCopy $default) {}

    #[Sinful(DataToArrayRoundtrip::class)]
    public function make(?HeaderCopy $header): HeaderHolder
    {
        return HeaderHolder::from(['header' => ($header ?? $this->default)->toArray()]);
    }
}

/**
 * RIGHTEOUS: a bare `array` slot does NOT re-hydrate — `->toArray()` is the actual value wanted; and a
 * non-`Data` receiver's `toArray()` isn't a Data round-trip. Neither is flagged.
 */
final class MetaHolder extends Data
{
    public function __construct(public readonly array $meta, public readonly string $kind) {}
}

final class MetaBag
{
    public function toArray(): array
    {
        return [];
    }
}

final class MetaHolderBuilder
{
    #[Righteous(DataToArrayRoundtrip::class)]
    public function fromData(HeaderCopy $header): MetaHolder
    {
        return MetaHolder::from(['meta' => $header->toArray(), 'kind' => 'header']);
    }

    #[Righteous(DataToArrayRoundtrip::class)]
    public function fromBag(MetaBag $bag): MetaHolder
    {
        return MetaHolder::from(['meta' => $bag->toArray(), 'kind' => 'bag']);
    }
}
