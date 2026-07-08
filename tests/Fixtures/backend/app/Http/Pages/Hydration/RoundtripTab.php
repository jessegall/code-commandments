<?php

namespace Shop\Http\Pages\Hydration;

use JesseGall\CodeCommandments\Sins\Backend\Spatie\DataToArrayRoundtrip;
use JesseGall\CodeCommandments\Testing\Sinful;
use Spatie\LaravelData\Data;

/*
 * N4 scenario 2 — a freshly-built `TabCopy::from($model)->toArray()` fed into a `TabCopy` slot that rebuilds
 * it: from → array → from, in a class that also carries an index.
 */
final class TabHolder extends Data
{
    public function __construct(public readonly TabCopy $tab, public readonly int $index) {}
}

final class TabHolderFactory
{
    /** @var array<string, int> */
    private array $order = ['edit' => 0, 'preview' => 1, 'diff' => 2];

    #[Sinful(DataToArrayRoundtrip::class)]
    public function forKind(object $model, string $kind): TabHolder
    {
        $index = $this->order[$kind] ?? count($this->order);

        return TabHolder::from(['tab' => TabCopy::from($model)->toArray(), 'index' => $index]);
    }
}
