<?php

namespace Shop\Http\Pages;

use JesseGall\CodeCommandments\Sins\Backend\Spatie\ConstructorOrchestration;
use JesseGall\CodeCommandments\Testing\Righteous;
use Spatie\LaravelData\Attributes\FromContainer;
use Spatie\LaravelData\Attributes\Hidden;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Lazy;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The real Smart Farmers WarehouseShowPage pattern of LEGITIMATE constructor work — the two kinds the
 * orchestration detector must not flag: a `Lazy` slot (hoisting it would destroy the deferral), and
 * slots derived from a local unwrapped once and reused (a `get` hook can't see that local). Righteous.
 */
#[TypeScript]
final class WarehousePage extends Data
{
    public readonly StatCard $summary;

    public readonly MenuLink $home;

    public readonly array $movers;

    #[Righteous(ConstructorOrchestration::class)]
    public function __construct(
        #[Hidden]
        #[FromContainer(SalesReporter::class)]
        public readonly SalesReporter $sales,
    ) {
        $totals = $this->sales->totals();
        $this->summary = $totals->summary();
        $this->home = $totals->homeLink();
        $this->movers = Lazy::closure(fn (): array => $this->sales->movers());
    }
}
