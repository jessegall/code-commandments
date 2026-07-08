<?php

namespace Shop\Http\Pages\RepeatedCall;

use Spatie\LaravelData\Data;

/*
 * The shared `with`-style trait, the element that uses it, and the payload Data classes the repeated-call
 * hydrators build. Declared once here (no findings of their own).
 */

trait WithChanges
{
    public function copyWith(mixed ...$changes): static
    {
        return $this;
    }
}

final class UiNode
{
    use WithChanges;
}

final class CardMeta extends Data
{
    public function __construct(public readonly string $title, public readonly string $tone) {}
}

final class PortMeta extends Data
{
    public function __construct(public readonly string $name, public readonly bool $required) {}
}

final class PanelMeta extends Data
{
    public function __construct(public readonly string $heading) {}
}
